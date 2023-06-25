#!/usr/bin/env python3

# apt install python3-selenium python3-pip
# and
# pip install selenium-wire

# and:
# https://github.com/mozilla/geckodriver/releases → /opt/geckodriver

import time
from seleniumwire import webdriver
from selenium.webdriver.firefox.service import Service

driver = webdriver.Firefox(service=Service(executable_path="/opt/geckodriver"))
driver.get("https://soundcloud.com/")

clid = None

while clid is None:
    for request in driver.requests:
        if request.url.startswith("https://api-v2.") \
        and "client_id" in request.url \
        and request.response and request.response.status_code == 200:
            clid = request.url.split("client_id=",1)[1].split("&",1)[0]
            break
    if clid is None: time.sleep(0.1)

driver.quit()

print(clid)

