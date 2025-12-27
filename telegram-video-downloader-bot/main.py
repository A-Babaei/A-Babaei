import asyncio
import logging
import os
import re
from dotenv import load_dotenv

from aiogram import Bot, Dispatcher, types, BaseMiddleware
from aiogram.filters import CommandStart
from aiogram.types import FSInputFile, Message
from aiogram.utils.markdown import hbold
from typing import Callable, Dict, Any, Awaitable

import yt_dlp

# Load environment variables from .env file
load_dotenv()

BOT_TOKEN = os.getenv("BOT_TOKEN")
OWNER_ID = int(os.getenv("OWNER_ID"))

# Basic logging configuration
logging.basicConfig(level=logging.INFO)

# Initialize bot and dispatcher
bot = Bot(token=BOT_TOKEN)
dp = Dispatcher()

# Middleware to check for owner ID
class OwnerFilterMiddleware(BaseMiddleware):
    async def __call__(
        self,
        handler: Callable[[Message, Dict[str, Any]], Awaitable[Any]],
        event: Message,
        data: Dict[str, Any]
    ) -> Any:
        if event.from_user.id != OWNER_ID:
            logging.warning(f"Blocked access for user {event.from_user.id}")
            return
        return await handler(event, data)

# Register the middleware
dp.update.outer_middleware.register(OwnerFilterMiddleware())


@dp.message(CommandStart())
async def handle_start(message: types.Message):
    """
    Handles the /start command.
    """
    await message.reply(
        f"Hi {hbold(message.from_user.full_name)}!\n\n"
        "I'm your personal video downloader bot.\n"
        "Just send me a link to a video from a supported platform "
        "(like YouTube, Instagram, TikTok, etc.), and I'll download it and send it back to you."
    )


URL_REGEX = r"(?P<url>https?://[^\s]+)"


def download_video_sync(url, ydl_opts):
    """Synchronous function to download a video."""
    with yt_dlp.YoutubeDL(ydl_opts) as ydl:
        info = ydl.extract_info(url, download=True)
        return ydl.prepare_filename(info)


@dp.message(lambda message: re.search(URL_REGEX, message.text))
async def handle_url(message: types.Message):
    """
    Handles messages containing a URL.
    """
    url = re.search(URL_REGEX, message.text).group("url")
    status_message = await message.reply("Downloading...")
    filename = None

    ydl_opts = {
        'format': 'bestvideo[ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best',
        'outtmpl': 'downloads/%(title)s.%(ext)s',
    }

    try:
        # Run the synchronous download function in a separate thread
        filename = await asyncio.to_thread(download_video_sync, url, ydl_opts)

        await status_message.edit_text("Uploading...")

        video = FSInputFile(filename)
        file_size = os.path.getsize(filename)

        if file_size < 50 * 1024 * 1024:  # 50 MB
            await message.reply_video(video)
        else:
            await message.reply_document(video)

    except Exception as e:
        logging.error(f"Error processing video: {e}")
        await status_message.edit_text("Sorry, an error occurred. Please check the URL and try again.")
    finally:
        if filename and os.path.exists(filename):
            os.remove(filename)
        await status_message.delete()


async def main() -> None:
    """
    Starts the bot.
    """
    if not os.path.exists("downloads"):
        os.makedirs("downloads")
    await dp.start_polling(bot)


if __name__ == "__main__":
    asyncio.run(main())
