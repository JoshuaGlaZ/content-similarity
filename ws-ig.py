from selenium import webdriver
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.common.by import By
from selenium.webdriver.support.wait import WebDriverWait
from selenium.webdriver.common.action_chains import ActionChains
from selenium.common.exceptions import (
    TimeoutException,
    NoSuchElementException,
    StaleElementReferenceException
)
import time
import pickle
import os
import sys
import json
from fake_useragent import UserAgent

COOKIE_FILE = "instagram_cookies_ws2.pkl" # Change if error google.com/null or ig warning. If still bugged, change driver to chrome
MAX_POSTS = 3
MAX_COMMENTS = 3
MAX_REPLIES = 3


def configure_chrome_options():
    options = Options()
    ua = UserAgent()
    options.add_argument(f'user-agent={ua.random}')
    options.add_argument('--headless') # Run Chrome in headless mode (without UI) 

    return options

# Use cookies sessionid to avoid account being banned

def save_cookies(driver, file_path):
    """Save browser cookies to a file for future sessions"""
    with open(file_path, "wb") as file:
        pickle.dump(driver.get_cookies(), file)

def load_cookies(driver, file_path):
    """Load saved cookies into the browser session"""
    with open(file_path, "rb") as file:
        for cookie in pickle.load(file):
            driver.add_cookie(cookie)

def login_instagram(driver):
    """
    Handle Instagram login process
    - Cookie = instagram.com -> reload with cookie -> done
    - Manual = instagram.com -> username & password -> submit -> save_cookies() -> done
    
    1. Opens Instagram login page
    2. Waits for login form elements to be clickable
    3. Enters credentials (username: ws.agent0011)
    4. Submits login form
    5. Waits for login completion
    6. Saves cookies for future sessions
    """
    driver.get("https://www.instagram.com")

    # Wait for and fill in login form
    username_field = WebDriverWait(driver, 5).until(
        EC.element_to_be_clickable((By.NAME, "username")))
    password_field = WebDriverWait(driver, 5).until(
        EC.element_to_be_clickable((By.NAME, "password")))
    
    # Enter credentials
    username_field.clear()
    username_field.send_keys("ws.agent0011")
    password_field.clear()
    password_field.send_keys("dummypassword")
    
    # Submit login form
    login_button = WebDriverWait(driver, 5).until(
        EC.element_to_be_clickable((By.CSS_SELECTOR, "button[type='submit']")))
    login_button.click()

    # Wait for login to complete and save cookies
    time.sleep(5)
    save_cookies(driver, COOKIE_FILE)

def scrape_instagram(driver, keywords):
    """
    Extracts posts, comments, and replies from Instagram based on given keywords
    
    1. Search = After login, Google search for Instagram posts containing keywords
    2. Posts = For each search result up to MAX_POSTS:
       - URL handling
       - Extract post caption, comments, and replies
    3. Navigate = Move to next Google page if needed
    
    Extraction limits:
    - Posts: Up to MAX_POSTS (default: 3)
    - Comments per post: Up to MAX_COMMENTS (default: 3)
    - Replies per comment: Up to MAX_REPLIES (default: 3)
    
    URL handling:
    - Profile URLs (instagram.com/user/): Skipped
    - Direct URLs (instagram.com/p/id/): Modified to include username
    - Full URLs (instagram.com/user/p/id/): Processed directly
    """
    # Format keywords for Google search
    keywords = '+'.join(keywords.split(' '))
    search_url = f"https://www.google.com/search?q=site%3Ainstagram.com+intext%3A{keywords}"
    driver.get(search_url)

    post_count = 0

    # Main loop to collect posts
    while post_count < MAX_POSTS:
        # Get all Instagram links from Google search results
        try:
            results = WebDriverWait(driver, 5).until(
                EC.presence_of_all_elements_located((By.CSS_SELECTOR, "a h3"))
            )
            result_links = [result.find_element(
                By.XPATH, "..").get_attribute("href") for result in results]
        except TimeoutException:
            break

        # Process each link
        for link in result_links:
            if post_count >= MAX_POSTS:
                break
              
            # Reset counters for new post
            comment_count = 0
            reply_count = 0

            # Open post in new tab
            driver.execute_script("window.open(arguments[0], '_blank');", link)
            driver.switch_to.window(driver.window_handles[1])
            WebDriverWait(driver, 5).until(
                lambda d: d.current_url != "about:blank")

            url_parts = driver.current_url.split('/')
            
            # Skip profile URLs
            if len(url_parts) == 5:
                driver.close()
                driver.switch_to.window(driver.window_handles[0])
                continue

            # Handle direct post/reel URLs
            elif len(url_parts) == 6:
                try:
                    # Get username from post
                    user_post = WebDriverWait(driver, 5).until(
                        EC.presence_of_element_located((By.CSS_SELECTOR, "span._ap3a._aaco._aacw._aacx._aad7._aade"))
                    ).text

                    # Reconstruct URL with username
                    # https://www.instagram.com/p/g2rewaf -> https://www.instagram.com/userX/p/g2rewaf
                    if user_post:
                        url_parts.insert(3, user_post)
                        full_url = '/'.join(url_parts)
                        driver.get(full_url)
                    # Skip if not found the username
                    else:
                        driver.close()
                        driver.switch_to.window(driver.window_handles[0])
                        continue
                except TimeoutException:
                    driver.close()
                    driver.switch_to.window(driver.window_handles[0])
                    continue

            text = ''
            try:
                # POST
                post_content = WebDriverWait(driver, 5).until(
                    EC.presence_of_all_elements_located((By.CSS_SELECTOR, "ul._a9z6._a9za > div")))
                post_caption = post_content[0].find_element(By.CSS_SELECTOR, "h1").text
                post_count += 1
                text = post_caption

                comments = post_content[-1].find_elements(By.CSS_SELECTOR, "ul._a9ym")
                time.sleep(2)

                for comment_num, comment in enumerate(comments):
                    if comment_count >= MAX_COMMENTS:
                        break
                        
                    try:
                        # COMMENT
                        user_comment = comment.find_element(By.CSS_SELECTOR, "h3 a").text
                        comment_text = comment.find_element(By.CSS_SELECTOR, "div.xt0psk2 span").text
                        text += f"\nKOMENTAR {comment_num + 1}: {user_comment}: {comment_text}"
                        comment_count += 1

                        try:
                            more_replies = comment.find_element(By.CSS_SELECTOR, 'ul._a9yo')
                            
                            # Clikc "more replies" if available
                            if more_replies.is_displayed():
                                driver.execute_script(
                                    "arguments[0].scrollIntoView({behavior: 'smooth', block: 'center'});",
                                    more_replies
                                )
    
                                # Explicitly wait until the element is clickable (avoiding popups or overlays)
                                WebDriverWait(driver, 2).until(EC.element_to_be_clickable(more_replies))
                                more_replies.click()
                                time.sleep(2)

                            replies = comment.find_elements(By.CSS_SELECTOR, "ul._a9yo div._a9zm")
                            for reply_num, reply in enumerate(replies):
                                if reply_count >= MAX_REPLIES:
                                    break
                                try:
                                    # REPLY
                                    user_reply = reply.find_element(By.CSS_SELECTOR, "h3 a").text
                                    reply_text = reply.find_element(By.CSS_SELECTOR, "div.xt0psk2 span").text
                                    text += f"\n\tREPLY {reply_num + 1}: {user_reply}: {reply_text}"
                                    reply_count += 1
                                except NoSuchElementException:
                                    continue
                        except (NoSuchElementException, TimeoutException, StaleElementReferenceException):
                            pass
                    except NoSuchElementException:
                        continue

                # Save 
                posts_output.append({
                    "original-text": text,
                    'link': driver.current_url
                })

            except (TimeoutException, NoSuchElementException) as e:
                pass

            # Close post tab and return to search results
            driver.close()
            driver.switch_to.window(driver.window_handles[0])

        # Navigate to next page of search results if needed
        if post_count < MAX_POSTS:
            try:
                next_button = WebDriverWait(driver, 5).until(
                    EC.element_to_be_clickable((By.XPATH, "//*[contains(text(), 'Berikutnya')]"))
                )
                next_button.click()
            except TimeoutException:
                break

def main():
    """Main execution function"""
    driver = webdriver.Chrome(options=configure_chrome_options())
    
    try:
        if os.path.exists(COOKIE_FILE):
            driver.get("https://www.instagram.com")
            load_cookies(driver, COOKIE_FILE)
            driver.refresh()
        else:
            login_instagram(driver)

        scrape_instagram(driver, sys.argv[1])
    finally:
        print(json.dumps(posts_output, indent=4))
        driver.delete_all_cookies()
        driver.quit()

posts_output = []

if __name__ == "__main__":
    main()