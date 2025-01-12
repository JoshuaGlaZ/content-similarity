# twitter_scraper.py
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import NoSuchElementException, TimeoutException
from selenium.webdriver.chrome.options import Options
from time import sleep
import json
import sys
from datetime import datetime
import argparse

class SimpleTwitterScraper:
    def __init__(self, username, password):
        self.username = username
        self.password = password
        self.tweets = []
        self.driver = self._setup_driver()
        
    def _setup_driver(self):
        options = Options()
        options.add_argument('--no-sandbox')
        options.add_argument('--disable-dev-shm-usage')
        options.add_argument('--disable-blink-features=AutomationControlled')
        options.add_argument('--disable-notifications')
        options.add_argument('--headless')
        return webdriver.Chrome(options=options)
    
    def _wait_and_find_element(self, by, value, timeout=10):
        try:
            element = WebDriverWait(self.driver, timeout).until(
                EC.presence_of_element_located((by, value))
            )
            return element
        except TimeoutException:
            return None

    def login(self):
        try:
            self.driver.get('https://twitter.com/i/flow/login')
            sleep(3)
            
            username_input = self._wait_and_find_element(
                By.CSS_SELECTOR, 
                'input[autocomplete="username"]'
            )
            if not username_input:
                raise Exception("Could not find username input field")
            
            username_input.send_keys(self.username)
            username_input.send_keys(Keys.RETURN)
            sleep(2)
            
            password_input = self._wait_and_find_element(
                By.CSS_SELECTOR,
                'input[name="password"]'
            )
            if not password_input:
                raise Exception("Could not find password input field")
            
            password_input.send_keys(self.password)
            password_input.send_keys(Keys.RETURN)
            sleep(5)
            
            self._wait_and_find_element(By.CSS_SELECTOR, 'a[aria-label="Home"]')
                
        except Exception as e:
            self.output_error(str(e))
            sys.exit(1)
    
    def search_tweets(self, query, max_tweets=10):
        try:
            self.driver.get(f'https://twitter.com/search?q={query}&src=typed_query&f=live')
            sleep(10)
            
            tweet_count = 0
            last_height = self.driver.execute_script("return document.body.scrollHeight")
            
            while tweet_count < max_tweets:
                tweet_cards = self.driver.find_elements(By.XPATH, '//article[@data-testid="tweet"]')
                
                for card in tweet_cards[tweet_count:]:
                    if tweet_count >= max_tweets:
                        break
                        
                    try:
                        username = card.find_element(By.XPATH, './/div[@data-testid="User-Name"]//span').text
                        text = card.find_element(By.XPATH, './/div[@data-testid="tweetText"]').text
                        
                        self.tweets.append({
                            'username': username,
                            'text': text,
                        })
                        tweet_count += 1
                        
                    except NoSuchElementException:
                        continue
                
                self.driver.execute_script("window.scrollTo(0, document.body.scrollHeight);")
                sleep(2)
                
                new_height = self.driver.execute_script("return document.body.scrollHeight")
                if new_height == last_height:
                    break
                last_height = new_height
            
            self.output_success()
            
        except Exception as e:
            self.output_error(str(e))
            
    def output_success(self):
        result = {
            'status': 'success',
            'data': self.tweets
        }
        print(json.dumps(result))
        
    def output_error(self, error_message):
        result = {
            'status': 'error',
            'message': error_message
        }
        print(json.dumps(result))
    
    def close(self):
        self.driver.close()
        self.driver.quit()

def main():
    parser = argparse.ArgumentParser(description='Twitter Scraper')
    parser.add_argument('query', type=str)
    parser.add_argument('--max_tweets', type=int, default=10)
    
    args = parser.parse_args()
    
    scraper = SimpleTwitterScraper(
        username="FSuhargo123",
        password="31245678Ab_"
    )
    
    try:
        scraper.login()
        scraper.search_tweets(args.query, max_tweets=args.max_tweets)
    finally:
        scraper.close()

if __name__ == "__main__":
    main()