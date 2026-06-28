from __future__ import annotations

import sys
from pathlib import Path

Path(__file__).parent.joinpath("tools").add_to_path = True
sys.path.insert(0, str(Path(__file__).parent))

from server import main

if __name__ == "__main__":
    main()