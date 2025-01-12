from selenium import webdriver
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.firefox.options import Options
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.common.by import By
from selenium.webdriver.support.wait import WebDriverWait
from selenium.webdriver.common.action_chains import ActionChains
from selenium.common.exceptions import TimeoutException, NoSuchElementException, StaleElementReferenceException
import time
import pickle
import os
import sys
import json
from fake_useragent import UserAgent

# Configure firefox options
options = Options()
ua = UserAgent()
options.add_argument(f'user-agent={ua.random}')
options.add_argument('--disable-notifications')
options.add_argument('--disable-images')
options.add_argument('--disable-web-security')
options.add_argument('--disable-blink-features=AutomationControlled')
options.add_argument('--disable-webrtc')
options.add_argument('--disable-media-stream')
options.add_argument('--enable-tracking-protection')

cookie = "instagram_cookies_ws11.pkl"

keywords = sys.argv[1]

posts_output = []

max_post = 3
max_comment = 3
max_reply = 3

def save_cookies(driver, file_path):
    with open(file_path, "wb") as file:
        pickle.dump(driver.get_cookies(), file)

def load_cookies(driver, file_path):
    with open(file_path, "rb") as file:
        for cookie in pickle.load(file):
            driver.add_cookie(cookie)

def login_instagram(driver):
    '''
    Login bisa pakai cookies pickle | load_cookies() ato isi manual | send_keys -> save_cookies(). 
    - Cookie = instagram.com -> reload pake cookie -> done
    - Manual = instagram.com -> isi username dan password -> submit -> save_cookies() -> done
    '''
    driver.get("https://www.instagram.com")

    username = WebDriverWait(driver, 10).until(
        EC.element_to_be_clickable((By.NAME, "username")))
    password = WebDriverWait(driver, 10).until(
        EC.element_to_be_clickable((By.NAME, "password")))
    username.clear()
    username.send_keys("ws.agent0011")
    password.clear()
    password.send_keys("dummypassword")
    login_button = WebDriverWait(driver, 10).until(
        EC.element_to_be_clickable((By.CSS_SELECTOR, "button[type='submit']")))
    login_button.click()

    time.sleep(4)
    save_cookies(driver, cookie)

def scrape_instagram(driver, keywords):
    keywords = '+'.join(keywords.split(' '))
    driver.get(
        f"https://www.google.com/search?q=site%3Ainstagram.com+intext%3A{keywords}")

    post_count = 0

    while post_count < max_post:
        result_links = []

        try:
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
              
            comment_count = 0
            reply_count = 0

            driver.execute_script("window.open(arguments[0], '_blank');", link)
            driver.switch_to.window(driver.window_handles[1])
            WebDriverWait(driver, 10).until(
                lambda d: d.current_url != "about:blank")

            # If without content with link == https://www.instagram.com/userX/, skip
            if len(driver.current_url.split('/')) == 5:
                driver.close()
                driver.switch_to.window(driver.window_handles[0])
                continue

            # If without username with link == https://www.instagram.com/p/g2rewaf/ or https://www.instagram.com/reel/g2rewaf/, 
            # re-search -> https://www.instagram.com/userX/p/g2rewaf/ or https://www.instagram.com/userX/reel/g2rewaf/
            # but if theres no username (undefined link), skip
            elif len(driver.current_url.split('/')) == 6:
                # Get the post user's username 
                user_post = WebDriverWait(driver, 5).until(EC.presence_of_element_located(
                    (By.CSS_SELECTOR, "span._ap3a._aaco._aacw._aacx._aad7._aade"))).text

                current_url = driver.current_url.split('/')
                if user_post:
                    current_url.insert(3, user_post)
                    current_url = '/'.join(current_url)
                    driver.get(current_url)
                else:
                    driver.close()
                    driver.switch_to.window(driver.window_handles[0])
                    continue
            
            text = ''
            
            post_content = WebDriverWait(driver, 10).until(
                    EC.presence_of_all_elements_located((By.CSS_SELECTOR, "ul._a9z6._a9za > div")))
            
            post_caption = post_content[0].find_element(By.CSS_SELECTOR, "h1").text

            post_count += 1
            text = post_caption

            comments = post_content[-1].find_elements(By.CSS_SELECTOR, "ul._a9ym")
            time.sleep(2)

            for comment_num, comment in enumerate(comments):
                if comment_count >= max_comment:
                    break
                try:
                    # Extract main comment details
                    user_comment = comment.find_element(By.CSS_SELECTOR, "h3 a").text
                    comment_text = comment.find_element(By.CSS_SELECTOR, "div.xt0psk2 span").text
                    text += f"\nKOMENTAR {comment_num + 1}: {user_comment}: {comment_text}"
                    comment_count += 1
                except NoSuchElementException:
                    continue

                try:
                    # Locate the "more replies" section
                    more_replies = comment.find_element(By.CSS_SELECTOR, 'ul._a9yo')
                    
                    if more_replies.is_displayed():
                        driver.execute_script("arguments[0].scrollIntoView({behavior: 'smooth', block: 'center'});", more_replies) # Scroll to more replies
                        more_replies.click()  
                        time.sleep(2)
                        
                        # Extract replies for this comment
                    replies = comment.find_elements(By.CSS_SELECTOR, "ul._a9yo div._a9zm")
                    for reply_num, reply in enumerate(replies):
                        if reply_count >= max_reply:
                            break
                        try:
                            user_reply = reply.find_element(By.CSS_SELECTOR, "h3 a").text
                            reply_text = reply.find_element(By.CSS_SELECTOR, "div.xt0psk2 span").text
                            text += f"\n\tREPLY {reply_num + 1}: {user_reply}: {reply_text}"
                            reply_count += 1
                        except NoSuchElementException:
                            continue
                except (NoSuchElementException, TimeoutException, StaleElementReferenceException):
                    pass  
                  
            posts_output.append({"original-text": text, 'link': driver.current_url})

            driver.close()
            driver.switch_to.window(driver.window_handles[0])

        # Click google page if not enough data
        if post_count < max_post:
            try:
                next_button = WebDriverWait(driver, 5).until(
                    EC.element_to_be_clickable(
                        (By.XPATH, "//*[contains(text(), 'Berikutnya')]"))
                )
                next_button.click()
            except TimeoutException:
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