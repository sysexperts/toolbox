import os
from pathlib import Path


class Config:
    SECRET_KEY = os.environ.get("SECRET_KEY", "dev-secret-key-change-me")
    WTF_CSRF_TIME_LIMIT = None
    SESSION_COOKIE_HTTPONLY = True
    SESSION_COOKIE_SECURE = False  # Set to True behind HTTPS reverse proxy
    SESSION_COOKIE_SAMESITE = "Lax"
    TEMPLATES_AUTO_RELOAD = True
    PREFERRED_URL_SCHEME = "https"
    # Path for storing localized content, privacy notice etc.
    BASE_DIR = Path(__file__).resolve().parent
