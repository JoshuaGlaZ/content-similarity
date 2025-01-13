from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import NoSuchElementException, TimeoutException
from selenium.webdriver.chrome.options import Options
from time import sleep
import sys
import json
from datetime import datetime

def setup_driver():
    """Setup and return Chrome driver with basic options"""
    options = Options()
    options.add_argument('--no-sandbox')
    options.add_argument('--disable-dev-shm-usage')
    options.add_argument('--disable-blink-features=AutomationControlled')
    options.add_argument('--disable-notifications')
    # options.add_argument('--headless')
    return webdriver.Chrome(options=options)

def wait_for_element(driver, by, value, timeout=10):
    """Wait for element to be present and return it"""
    try:
        element = WebDriverWait(driver, timeout).until(
            EC.presence_of_element_located((by, value))
        )
        return element
    except TimeoutException:
        print(f"Timeout waiting for element: {value}", file=sys.stderr)
        return None

def login_to_twitter(driver, username, password):
    """Login to Twitter"""
    try:
        driver.get('https://x.com/i/flow/login')
        sleep(10)
        
        username_input = wait_for_element(
            driver,
            By.CSS_SELECTOR, 
            'input[autocomplete="username"]'
        )
        if not username_input:
            raise Exception("Could not find username input field")
        
        username_input.send_keys(username)
        username_input.send_keys(Keys.RETURN)
        sleep(2)
        
        password_input = wait_for_element(
            driver,
            By.CSS_SELECTOR,
            'input[name="password"]'
        )
        if not password_input:
            raise Exception("Could not find password input field")
        
        password_input.send_keys(password)
        password_input.send_keys(Keys.RETURN)
        sleep(5)
        
        wait_for_element(driver, By.CSS_SELECTOR, 'a[aria-label="Home"]')
        return True
        
    except Exception as e:
        print(f"Login failed: {str(e)}", file=sys.stderr)
        return False

def search_tweets(driver, query, max_tweets=10):
    """Search and collect tweets"""
    tweets = []
    
    try:
        driver.get(f'https://x.com/search?q={query}&src=typed_query&f=live')
        sleep(10)
        
        tweet_count = 0
        last_height = driver.execute_script("return document.body.scrollHeight")
        
        while tweet_count < max_tweets:
            tweet_cards = driver.find_elements(By.XPATH, '//article[@data-testid="tweet"]')
            
            for card in tweet_cards[tweet_count:]:
                if tweet_count >= max_tweets:
                    break
                    
                try:
                    username = card.find_element(By.XPATH, './/div[@data-testid="User-Name"]//span').text
                    text = card.find_element(By.XPATH, './/div[@data-testid="tweetText"]').text
                    
                    tweet_data = {
                        "username": username,
                        "text": text,
                    }
                    
                    tweets.append(tweet_data)
                    tweet_count += 1
                    
                except NoSuchElementException:
                    continue
            
            driver.execute_script("window.scrollTo(0, document.body.scrollHeight);")
            sleep(2)
            
            new_height = driver.execute_script("return document.body.scrollHeight")
            if new_height == last_height:
                break
            last_height = new_height
            
    except Exception as e:
        print(f"Error collecting tweets: {str(e)}", file=sys.stderr)
    
    return tweets

def main():
    if len(sys.argv) < 2:
        print("Usage: python script.py <search_query> [max_tweets]", file=sys.stderr)
        return {}
    
    query = sys.argv[1]
    max_tweets = int(sys.argv[2]) if len(sys.argv) > 2 else 10
    
    driver = setup_driver()
    tweets = []
    
    try:
        if login_to_twitter(driver, "test130973", "31245678Ab_"):
            tweets = search_tweets(driver, query, max_tweets)
    finally:
        driver.close()
        driver.quit()
    
    # Create result dictionary with metadata
    result = {
        "metadata": {
            "query": query,
            "max_tweets": max_tweets,
        },
        "tweets": tweets
    }
    
    return result

if __name__ == "__main__":
    result = main()
    # Print the JSON result to stdout
    print(json.dumps(result, indent=2, ensure_ascii=False))