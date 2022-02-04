#!/usr/bin/env python3

# apt install python3-selenium
# - or -
# pip install selenium

# and:
# https://github.com/mozilla/geckodriver/releases → /opt/geckodriver

from selenium import webdriver
from selenium.webdriver.firefox.options import Options
from selenium.webdriver.firefox.service import Service

service = Service(executable_path="/opt/geckodriver")
options = Options()

driver = webdriver.Firefox(options=options, service=service)
driver.get("https://soundcloud.com")
cid = driver.page_source.split("client_id=",1)[1].split("&",1)[0]
driver.quit()

print(cid)
