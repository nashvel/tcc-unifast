"""Centralized application configuration."""

from functools import lru_cache
from pathlib import Path
from typing import Optional

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    """Runtime settings loaded from environment variables."""

    tesseract_cmd: Optional[str] = None
    tesseract_psm: int = 6
    save_debug_images: bool = False
    output_dir: Path = Path("outputs")
    max_image_size_bytes: int = 10 * 1024 * 1024
    max_pdf_size_bytes: int = 20 * 1024 * 1024
    max_pdf_pages: int = 20

    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        case_sensitive=False,
    )

    @property
    def tesseract_config(self) -> str:
        """Return the pytesseract command-line configuration."""
        return f"--oem 3 --psm {self.tesseract_psm}"


@lru_cache
def get_settings() -> Settings:
    """Return cached settings for dependency injection."""
    return Settings()

