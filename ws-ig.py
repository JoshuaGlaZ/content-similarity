from selenium import webdriver
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.firefox.options import Options
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.common.by import By
from selenium.webdriver.support.wait import WebDriverWait
from selenium.common.exceptions import TimeoutException, NoSuchElementException, StaleElementReferenceException
import time
import pickle
import os
import sys
import json
from fake_useragent import UserAgent

# Configure Firefox options
options = Options()
ua = UserAgent()  # Random user agent
# options.add_argument("-headless")  # Run in headless mode
options.set_preference("general.useragent.override",
                       ua.random)  # Set a random user agent
options.set_preference("dom.webdriver.enabled",
                       False)  # Disable webdriver flag
options.set_preference("dom.webnotifications.enabled",
                       False)  # Disable notifications
# # Enable tracking protection
options.set_preference("privacy.trackingprotection.enabled", True)
# # Disable images for faster loading (1 = enabled, 2 = disabled)
options.set_preference("permissions.default.image", 2)
# options.set_preference("permissions.default.stylesheet", 2)  # Disable CSS
# # Disable WebRTC to prevent IP leakage
options.set_preference("media.peerconnection.enabled", False)
# # Disable media devices to avoid detection
options.set_preference("media.navigator.enabled", False)

cookie = "instagram_cookies.pkl"

keywords = sys.argv[1]  # ws-ig.py "piala dunia 2000"

posts_output = []

max_post = 3
max_comment = 3
max_reply = 3
post_num = 0


def save_cookies(driver, file_path):
    with open(file_path, "wb") as file:
        pickle.dump(driver.get_cookies(), file)


def load_cookies(driver, file_path):
    with open(file_path, "rb") as file:
        for cookie in pickle.load(file):
            driver.add_cookie(cookie)


def login_instagram(driver):
    driver.get("https://www.instagram.com")

    username = WebDriverWait(driver, 10).until(
        EC.element_to_be_clickable((By.NAME, "username")))
    password = WebDriverWait(driver, 10).until(
        EC.element_to_be_clickable((By.NAME, "password")))
    username.clear()
    # username.send_keys("ws.agent0001")
    username.send_keys("ttusj_7_.1")
    password.clear()
    # password.send_keys("dummypassword")
    password.send_keys("dummypassword")
    login_button = WebDriverWait(driver, 10).until(
        EC.element_to_be_clickable((By.CSS_SELECTOR, "button[type='submit']")))
    login_button.click()

    WebDriverWait(driver, 10).until(EC.presence_of_element_located(
        (By.CSS_SELECTOR, "svg[aria-label='Beranda']")))
    save_cookies(driver, cookie)


def safe_click(driver, element):
    retries = 3
    for _ in range(retries):
        try:
            element.click()
            return
        except StaleElementReferenceException:
            time.sleep(0.1)
    raise TimeoutException("Failed to click the element after retries")


def scrape_instagram(driver, keywords):
    keywords = '+'.join(keywords.split(' '))
    driver.get(
        f"https://www.google.com/search?q=site%3Ainstagram.com+intext%3A{keywords}")

    post_count = 0

    while post_count < max_post:
        result_links = []

        try:
            # Retrieve result links
            results = WebDriverWait(driver, 5).until(
                EC.presence_of_all_elements_located((By.CSS_SELECTOR, "a h3"))
            )
            result_links = [result.find_element(
                By.XPATH, "..").get_attribute("href") for result in results]
        except TimeoutException:
            break

        for link in result_links:
            if post_count >= max_post:
                break

            driver.execute_script("window.open(arguments[0], '_blank');", link)
            driver.switch_to.window(driver.window_handles[1])
            WebDriverWait(driver, 10).until(
                lambda d: d.current_url != "about:blank")

            # "https://www.instagram.com/officialinewstv/"
            if len(driver.current_url.split('/')) == 5:
                driver.close()
                driver.switch_to.window(driver.window_handles[0])
                continue

            # "https://www.instagram.com/p/C93AkvGBlci/?hl=af"
            elif len(driver.current_url.split('/')) == 6:
                user_post = WebDriverWait(driver, 5).until(EC.presence_of_element_located(
                    (By.CSS_SELECTOR, "span._ap3a._aaco._aacw._aacx._aad7._aade"))).text

                current_url = driver.current_url.split('/')
                if user_post:  # Check if user_post is not empty
                    current_url.insert(3, user_post)
                    current_url = '/'.join(current_url)
                    driver.get(current_url)
                else:
                    driver.close()
                    driver.switch_to.window(driver.window_handles[0])
                    continue

            try:
                post_caption = WebDriverWait(driver, 10).until(
                    EC.presence_of_element_located((By.CSS_SELECTOR, "h1"))).text
            except TimeoutException:
                post_caption = "No caption available for this post."

            post_count += 1
            # print(f"Post {post_count}: {post_caption}")
            # print(f"Link: {driver.current_url}")
            text = post_caption

            try:
                comments = WebDriverWait(driver, 5).until(
                    EC.presence_of_all_elements_located((By.CSS_SELECTOR, 'ul._a9ym')))
            except TimeoutException:
                comments = []

            for comment_num, comment in enumerate(comments):
                try:
                    user_comment = comment.find_element(
                        By.CSS_SELECTOR, 'h3._a9zc div a').text
                except NoSuchElementException:
                    user_comment = "No username found"

                try:
                    comment_text = comment.find_element(
                        By.CSS_SELECTOR, 'div._a9zs span._ap3a._aaco._aacu._aacx._aad7._aade').text
                except NoSuchElementException:
                    # If there’s no text, it could be an image-only comment
                    try:
                        image_element = comment.find_element(
                            By.CSS_SELECTOR, 'img')
                        comment_text = f"[Image: {
                            image_element.get_attribute('src')}]"
                    except NoSuchElementException:
                        comment_text = "No text or image in comment"

                # print(f"User {comment_num+1} by {user_comment}: {comment_text}")
                text += f"\n{user_comment}: {comment_text}" 

                # Click more replies
                click_reply = 0
                while click_reply < max_reply:
                    try:
                        more_replies = comment.find_element(
                            By.CSS_SELECTOR, 'li._a9yg')
                        if more_replies.is_displayed():
                            safe_click(driver, more_replies)
                            click_reply += 1
                    except (NoSuchElementException, TimeoutException, StaleElementReferenceException):
                        break

                # Load replies if available
                try:
                    comment_replies = comment.find_element(
                        By.CSS_SELECTOR, 'ul._a9yo')
                    replies = comment_replies.find_elements(
                        By.CSS_SELECTOR, 'div._a9zm')
                    for reply_num, reply in enumerate(replies[:max_reply]):
                        user_reply = reply.find_element(
                            By.CSS_SELECTOR, 'span.xt0psk2 div a').text
                        reply_text = reply.find_element(
                            By.CSS_SELECTOR, 'div._a9zs span').text

                        # print(f"\tReply {reply_num + 1} by {user_reply}: {reply_text}")
                        text += f"\n\t{user_reply}: {reply_text}" 

                except (NoSuchElementException, TimeoutException, StaleElementReferenceException):
                    break
                  
            posts_output.append({"original-text": text, 'link': driver.current_url})

            driver.close()
            driver.switch_to.window(driver.window_handles[0])

        # Attempt to load next page of results
        if post_count < max_post:
            try:
                next_button = WebDriverWait(driver, 5).until(
                    EC.element_to_be_clickable(
                        (By.XPATH, "//*[contains(text(), 'Berikutnya')]"))
                )
                next_button.click()
            except TimeoutException:
                # print("No more pages available.")
                break


driver = webdriver.Firefox(options=options)

try:
    if os.path.exists(cookie):
        driver.get("https://www.instagram.com")
        load_cookies(driver, cookie)
        driver.refresh()
    else:
        login_instagram(driver)

    scrape_instagram(driver, keywords)
finally:
    print(json.dumps(posts_output, indent=4))
    driver.delete_all_cookies()
    driver.quit()
