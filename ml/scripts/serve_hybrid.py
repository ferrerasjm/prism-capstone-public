#!/usr/bin/env python3
"""Serve the frozen PRISM offense-ranking model on localhost."""

from __future__ import annotations

import argparse
import json
import os
import sys
from http import HTTPStatus
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from typing import Any

from hybrid_inference import HybridInferenceError, HybridOffensePredictor


MAX_REQUEST_BYTES = 32_768


def parse_arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("--host", default="127.0.0.1")
    parser.add_argument("--port", type=int, default=8765)
    parser.add_argument("--token", default=os.environ.get("PRISM_ML_CLASSIFIER_TOKEN"))
    return parser.parse_args()


class PredictionServer(ThreadingHTTPServer):
    predictor: HybridOffensePredictor
    access_token: str | None


class PredictionHandler(BaseHTTPRequestHandler):
    server: PredictionServer

    def do_GET(self) -> None:  # noqa: N802
        if self.path != "/health":
            self._json(HTTPStatus.NOT_FOUND, {"message": "Not found"})
            return
        self._json(
            HTTPStatus.OK,
            {"status": "ok", "model": self.server.predictor.model_metadata},
        )

    def do_POST(self) -> None:  # noqa: N802
        if self.path != "/predict":
            self._json(HTTPStatus.NOT_FOUND, {"message": "Not found"})
            return
        if self.server.access_token and self.headers.get("Authorization") != (
            f"Bearer {self.server.access_token}"
        ):
            self._json(HTTPStatus.UNAUTHORIZED, {"message": "Unauthorized"})
            return

        try:
            length = int(self.headers.get("Content-Length", "0"))
            if length <= 0 or length > MAX_REQUEST_BYTES:
                raise ValueError("Invalid request size")
            payload: dict[str, Any] = json.loads(
                self.rfile.read(length).decode("utf-8")
            )
            text = payload.get("text")
            if not isinstance(text, str):
                raise ValueError("text must be a string")
            excluded = payload.get("excluded_labels", [])
            if not isinstance(excluded, list) or not all(
                isinstance(label, str) for label in excluded
            ):
                raise ValueError("excluded_labels must be a string array")
            result = self.server.predictor.predict(
                text,
                top_k=int(payload.get("top_k", 3)),
                excluded_labels=excluded,
            )
        except (ValueError, TypeError, json.JSONDecodeError) as error:
            self._json(HTTPStatus.UNPROCESSABLE_ENTITY, {"message": str(error)})
            return
        except Exception:
            # Incident narratives must never be included in service error output.
            self._json(
                HTTPStatus.INTERNAL_SERVER_ERROR,
                {"message": "Inference failed"},
            )
            return

        self._json(HTTPStatus.OK, result)

    def log_message(self, format: str, *args: object) -> None:
        # Log method/path/status only; request bodies are intentionally excluded.
        super().log_message(format, *args)

    def _json(self, status: HTTPStatus, payload: dict[str, Any]) -> None:
        body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
        self.send_response(status.value)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        self.wfile.write(body)


def main() -> int:
    arguments = parse_arguments()
    if arguments.host not in {"127.0.0.1", "localhost", "::1"}:
        print("Refusing to bind the advisory model outside localhost", file=sys.stderr)
        return 2
    if not 1 <= arguments.port <= 65_535:
        print("Port must be between 1 and 65535", file=sys.stderr)
        return 2

    try:
        predictor = HybridOffensePredictor()
    except (OSError, ValueError, KeyError, HybridInferenceError) as error:
        print(f"Unable to load frozen model: {error}", file=sys.stderr)
        return 1

    server = PredictionServer((arguments.host, arguments.port), PredictionHandler)
    server.predictor = predictor
    server.access_token = arguments.token
    metadata = predictor.model_metadata
    print(
        f"PRISM advisory model {metadata['version']} listening on "
        f"http://{arguments.host}:{arguments.port}",
        flush=True,
    )
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        server.server_close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
