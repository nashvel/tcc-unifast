"""Image metadata extraction without authenticity scoring."""

from PIL import ExifTags, Image

from app.schemas import MetadataInfo

EXIF_TAGS = {value: key for key, value in ExifTags.TAGS.items()}


def extract_metadata(image: Image.Image) -> MetadataInfo:
    """Extract basic image metadata without logging or scoring it."""
    exif = image.getexif()
    gps_tag = EXIF_TAGS.get("GPSInfo")

    def read_tag(name: str) -> str | None:
        tag_id = EXIF_TAGS.get(name)
        if tag_id is None:
            return None
        value = exif.get(tag_id)
        return str(value) if value is not None else None

    return MetadataInfo(
        width=image.width,
        height=image.height,
        image_format=image.format,
        camera_make=read_tag("Make"),
        camera_model=read_tag("Model"),
        date_taken=read_tag("DateTimeOriginal") or read_tag("DateTime"),
        software=read_tag("Software"),
        gps_present=bool(gps_tag and exif.get(gps_tag)),
    )

