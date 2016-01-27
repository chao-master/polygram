# DerpiDelivery
In-line Telegram bot for inserting images from Derpibooru

## Setup
1. Follow the [Telegram Bot setup](https://core.telegram.org/bots#botfather) Guide to create the bot and get your api key
1. Clone [Ploygram](https://github.com/chao-master/polygram) and cd into it
1. Clone DerpiDelivery into this directory calling it Derpibooru
1. Add your api key into Derpibooru/authkey
1. Edit Derpibooru/Derpibooru.php changing the line `const BOT_ID` and `const BOT_USERNAME` to the values from step 1
1. Follow https://core.telegram.org/bots/api#setwebhook to setup the webhook
```
git clone https://github.com/chao-master/polygram.git
cd polygram
git clone https://github.com/chao-master/DerpiDelivery.git Derpibooru
```
