"""
Smart WeatherNet - Master Forecast Dispatcher
Executes hourly forecast generation for Node 1 (SMVITM Campus) and Node 2.
Path: php-backend/generate_forecast.py
"""
import sys
import os
import subprocess
import argparse

BASE_DIR = os.path.dirname(os.path.abspath(__file__))

def run_node_forecast(node="1", dry_run=False):
    node_dir = os.path.join(BASE_DIR, f"node {node}")
    script_path = os.path.join(node_dir, "generate_forecast.py")
    if not os.path.exists(script_path):
        print(f"Error: Script not found: {script_path}", file=sys.stderr)
        return 1

    cmd = [sys.executable, script_path]
    if dry_run:
        cmd.append("--dry-run")

    print(f"=== Running Forecast Engine for Node {node} ===", file=sys.stderr)
    res = subprocess.run(cmd, cwd=node_dir)
    return res.returncode

def main():
    parser = argparse.ArgumentParser(description="Smart WeatherNet Master Forecast Dispatcher")
    parser.add_argument("--node", type=str, default="1", choices=["1", "2", "all"], help="Node target (default: 1)")
    parser.add_argument("--dry-run", action="store_true", help="Run without persisting to Supabase")
    args, unknown = parser.parse_known_args()

    if args.node == "all":
        code1 = run_node_forecast("1", args.dry_run)
        code2 = run_node_forecast("2", args.dry_run)
        sys.exit(code1 if code1 != 0 else code2)
    else:
        code = run_node_forecast(args.node, args.dry_run)
        sys.exit(code)

if __name__ == "__main__":
    main()
