"""Pydantic models shared by API responses and internal results."""

from typing import Any, Literal, Optional

from pydantic import BaseModel, Field


class ErrorDetail(BaseModel):
    code: str
    message: str


class ErrorResponse(BaseModel):
    success: Literal[False] = False
    error: ErrorDetail


class HealthResponse(BaseModel):
    success: Literal[True] = True
    status: str
    ocr_engine: Literal["tesseract"]
    tesseract_available: bool


class BoundingBox(BaseModel):
    x: int
    y: int
    width: int
    height: int


class OcrWord(BaseModel):
    text: str
    confidence: float
    bounding_box: BoundingBox


class PreprocessingInfo(BaseModel):
    original_width: int
    original_height: int
    processed_width: int
    processed_height: int
    deskew_angle: float = 0.0


class MetadataInfo(BaseModel):
    width: int
    height: int
    image_format: Optional[str] = None
    camera_make: Optional[str] = None
    camera_model: Optional[str] = None
    date_taken: Optional[str] = None
    software: Optional[str] = None
    gps_present: bool = False


class QrResult(BaseModel):
    found: bool
    value: Optional[str] = None
    bounding_box: list[list[float]] = Field(default_factory=list)


class OcrResult(BaseModel):
    raw_text: str
    cleaned_text: str
    average_confidence: float
    word_count: int
    words: list[OcrWord]
    processing_time_ms: int
    preprocessing: PreprocessingInfo


class ImageOcrResponse(BaseModel):
    success: Literal[True] = True
    document_type: Literal["image"] = "image"
    engine: Literal["tesseract"] = "tesseract"
    result: OcrResult
    metadata: MetadataInfo
    qr_code: QrResult


class PdfPageResult(BaseModel):
    page: int
    method: Literal["embedded_text", "tesseract_ocr"]
    text: str
    useful_embedded_text: bool
    ocr: Optional[OcrResult] = None


class PdfResult(BaseModel):
    page_count: int
    pages: list[PdfPageResult]
    combined_text: str
    processing_time_ms: int


class PdfOcrResponse(BaseModel):
    success: Literal[True] = True
    document_type: Literal["pdf"] = "pdf"
    engine: Literal["tesseract"] = "tesseract"
    result: PdfResult


class CliResult(BaseModel):
    success: bool
    document_type: Optional[str] = None
    engine: Optional[str] = None
    result: Optional[Any] = None
    error: Optional[ErrorDetail] = None

