"""SVG şablonu inceler: stdin JSON -> stdout JSON {width,height,slots}.

resvg gerektirmez (saf Python), böylece şablon yüklemede slotlar her zaman çıkarılır.
Payload: {"svg_path": "...", "resvg_bin": "..."}  (resvg_bin yok sayılır)
"""

import json
import sys

import _svgcommon as svg


def main():
    payload = json.load(sys.stdin)
    svg_path = payload["svg_path"]

    root = svg.load_tree(svg_path)
    width, height = svg.dimensions(root)

    result = {
        "width": width,
        "height": height,
        "slots": svg.slots_of(root),
    }
    sys.stdout.write(json.dumps(result, ensure_ascii=False))


if __name__ == "__main__":
    main()
