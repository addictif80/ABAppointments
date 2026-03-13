#!/usr/bin/env python3
"""ABPanel launcher."""

import uvicorn
from backend.core.config import settings

if __name__ == "__main__":
    uvicorn.run(
        "backend.app:app",
        host=settings.HOST,
        port=settings.PORT,
        reload=settings.DEBUG,
    )
