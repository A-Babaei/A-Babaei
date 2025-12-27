# Telegram Video Downloader Bot

This is a private Telegram bot that downloads videos from various websites using `yt-dlp` and sends them to you.

## Features

- Download videos from any site supported by `yt-dlp`.
- Sends videos under 50MB directly, and larger videos as documents.
- Private bot: only responds to the owner's commands.
- Progress feedback (Downloading... Uploading...).
- Cleans up downloaded files automatically.

## Setup Instructions

### 1. Get a Bot Token from BotFather

- Open Telegram and search for the `@BotFather` bot.
- Start a chat with BotFather and use the `/newbot` command.
- Follow the instructions to choose a name and username for your bot.
- BotFather will give you a **bot token**. Copy this token.

### 2. Get your Telegram User ID

- Search for the `@userinfobot` on Telegram.
- Start a chat with this bot and it will give you your **user ID**.

### 3. Configure the Bot

- Create a `.env` file in the same directory as the bot's `main.py` file.
- Add the following lines to the `.env` file, replacing the placeholder values with your bot token and user ID:

```
BOT_TOKEN=YOUR_BOT_TOKEN_HERE
OWNER_ID=YOUR_TELEGRAM_ID_HERE
```

### 4. Install Dependencies

- Make sure you have Python 3.7+ installed.
- Install the required Python packages using pip:

```
pip install -r requirements.txt
```

### 5. Run the Bot

- Run the bot with the following command:

```
python main.py
```

### 6. Hosting (Optional)

For the bot to run 24/7, you can host it on a cloud platform like:

- **Replit:** Easy to set up and has a free tier.
- **Render:** Offers free tiers for web services and background workers.
- **VPS (Virtual Private Server):** (e.g., DigitalOcean, Linode, AWS) gives you more control but requires more setup.

## Usage

- Once the bot is running, simply send it a message containing a video URL.
- The bot will download the video, send it to you, and then delete the local file.

## Limitations

- **Telegram File Size Limits:** Telegram has a 50MB limit for videos sent via the `send_video` method. Larger videos will be sent as documents (up to 2GB).
- **Website Blocking:** Some websites may block `yt-dlp` or have measures to prevent video downloads.
- **Copyright and Terms of Service:** Be aware of the copyright and terms of service of the websites you are downloading from. This bot is for personal use only.
