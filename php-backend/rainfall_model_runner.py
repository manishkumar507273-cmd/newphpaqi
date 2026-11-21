"""
Smart WeatherNet - Master Rainfall Model Runner Dispatcher
Path: php-backend/rainfall_model_runner.py
"""
import sys
import os
import subprocess

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
node1_runner = os.path.join(BASE_DIR, "node 1", "rainfall_model_runner.py")

if __name__ == "__main__":
    if os.path.exists(node1_runner):
        res = subprocess.run([sys.executable, node1_runner] + sys.argv[1:], cwd=os.path.dirname(node1_runner))
        sys.exit(res.returncode)
    else:
        print(f"Error: Not found: {node1_runner}", file=sys.stderr)
        sys.exit(1)
