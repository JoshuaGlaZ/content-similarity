from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import NoSuchElementException, TimeoutException
from selenium.webdriver.chrome.options import Options
from time import sleep
import argparse
from datetime import datetime

def setup_driver():
    """Setup and return Chrome driver with basic options"""
    options = Options()
    options.add_argument('--no-sandbox')
    options.add_argument('--disable-dev-shm-usage')
    options.add_argument('--disable-blink-features=AutomationControlled')
    options.add_argument('--disable-notifications')
    options.add_argument('--headless')
    return webdriver.Chrome(options=options)

def wait_for_element(driver, by, value, timeout=10):
    """Wait for element to be present and return it"""
    try:
        element = WebDriverWait(driver, timeout).until(
            EC.presence_of_element_located((by, value))
        )
        return element
    except TimeoutException:
        print(f"Timeout waiting for element: {value}")
        return None

def login_to_twitter(driver, username, password):
    """Login to Twitter"""
    print("Logging in to Twitter...")
    try:
        # Go to login page
        driver.get('https://twitter.com/i/flow/login')
        sleep(10)
        
        # Find and fill username
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
        
        # Find and fill password
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
        
        # Verify login
        wait_for_element(driver, By.CSS_SELECTOR, 'a[aria-label="Home"]')
        print("Login successful!")
        return True
        
    except Exception as e:
        print(f"Login failed: {str(e)}")
        return False

def search_tweets(driver, query, max_tweets=10):
    """Search and collect tweets"""
    tweets = []
    print(f"\nSearching for tweets about: {query}")
    
    try:
        driver.get(f'https://twitter.com/search?q={query}&src=typed_query&f=live')
        sleep(10)
        
        tweet_count = 0
        last_height = driver.execute_script("return document.body.scrollHeight")
        
        while tweet_count < max_tweets:
            # Find tweet cards
            tweet_cards = driver.find_elements(By.XPATH, '//article[@data-testid="tweet"]')
            
            for card in tweet_cards[tweet_count:]:
                if tweet_count >= max_tweets:
                    break
                    
                try:
                    # Extract tweet data
                    username = card.find_element(By.XPATH, './/div[@data-testid="User-Name"]//span').text
                    text = card.find_element(By.XPATH, './/div[@data-testid="tweetText"]').text
                    
                    # Store tweet
                    tweets.append([username, text])
                    tweet_count+=1
                    
                except NoSuchElementException:
                    continue
            
            # Scroll down
            driver.execute_script("window.scrollTo(0, document.body.scrollHeight);")
            sleep(2)
            
            # Check if we've reached the bottom
            new_height = driver.execute_script("return document.body.scrollHeight")
            if new_height == last_height:
                break
            last_height = new_height
            
    except Exception as e:
        print(f"Error collecting tweets: {str(e)}")
    
    return tweets

def main():
    # Set up argument parser
    parser = argparse.ArgumentParser(description='Twitter Scraper')
    parser.add_argument('query', type=str, help='Search query for tweets')
    parser.add_argument('--max_tweets', type=int, default=10, help='Maximum number of tweets to collect (default: 10)')
 
    
    args = parser.parse_args()
    
    # Initialize the web driver
    driver = setup_driver()
    
    try:
        # Login to Twitter
        if login_to_twitter(driver, "FSuhargo123", "31245678Ab_"):
            # Search and collect tweets
            tweets = search_tweets(driver, args.query, args.max_tweets)
            print(f"\nCollected {len(tweets)} tweets!")
            
            # Return tweets array
            print("\nTweets array:")
            print(tweets)
            
    finally:
        # Clean up
        driver.close()
        driver.quit()

if __name__ == "__main__":
    main()