import sys
import json
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

chrome_options = webdriver.ChromeOptions()
chrome_options.add_argument('--headless')
chrome_options.add_argument('--no-sandbox')
chrome_options.add_argument('--disable-dev-shm-usage')

wd_main = webdriver.Chrome(options=chrome_options)
query = '+'.join(sys.argv[1:])
wd_main.get(f'https://www.youtube.com/results?search_query={query}')

wait = WebDriverWait(wd_main, 10)
wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, 'h3.title-and-badge')))
data = []
video_links = []

for content in wd_main.find_elements(By.CSS_SELECTOR, 'h3.title-and-badge')[:3]:
    link = content.find_element(By.CSS_SELECTOR, 'a').get_attribute('href')
    title = content.find_element(By.CSS_SELECTOR, 'yt-formatted-string').text or 'no title'
    video_links.append((link,title))
wd_main.quit()

description_selectors = [
    "div#description yt-formatted-string", # utk hashtag dll
    # "div#description", # description yg belum di expand
    "div#description span.yt-core-attributed-string--link-inherit-color",
    # "yt-formatted-string"
]
comments_selectors = [
    'div#content.style-scope.ytd-expander span.yt-core-attributed-string.yt-core-attributed-string--white-space-pre-wrap',
    'yt-attributed-string#content-text span.yt-core-attributed-string.yt-core-attributed-string--white-space-pre-wrap',
    # 'yt-attributed-string span.yt-core-attributed-string.yt-core-attributed-string--white-space-pre-wrap'
]

for link,title in video_links:
    wd_detail = webdriver.Chrome(options=chrome_options)
    wd_detail.get(link)

    wait = WebDriverWait(wd_detail, 10)
    wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, 'ytd-item-section-renderer')))

    # deskripsi
    for selector in description_selectors:
        try: desc_vid =  wd_detail.find_elements(By.CSS_SELECTOR, f'{selector}')
        except: continue
    description = ''
    for i in desc_vid:
        description = description + f' {i.text.strip()}'

    # komentar
    for selector in comments_selectors:
        try: comments =  wd_detail.find_elements(By.CSS_SELECTOR, f'{selector}')
        except: continue
    comment = ''
    for i in comments[:3]:
        comment = comment + '<br>' + i.text.strip()
    
    data.append({
        'source': f'youtube - {title} - <a href="{link}">source link</a>',
        'original-text': f'{description} <br><br> <b>KOMENTAR</b> {comment} <br>',
        # 'preprocess-result': description,
        # 'similarity': 0.0
    })
    
    wd_detail.quit()
    
print(json.dumps(data))
