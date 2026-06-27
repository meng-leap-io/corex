#!/usr/bin/env python3
"""
Main Python script for running Corex.dev Laravel application as a Windows service.
This script provides a command-line interface for service management and
automatically loads environment variables from the Windows AppData folder.
"""

import sys
import os
import argparse
import time
import logging
import platform
from pathlib import Path
from typing import Dict, Any

# Add the current directory to Python path
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler(os.path.join(os.environ.get('APPDATA', ''), 'Corex', 'logs', 'service_wrapper.log')),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)
class CorexWindowsServiceManager:
    """Manages Windows service operations for Corex.dev"""

    def __init__(self):
        self.appdata_path = os.environ.get('APPDATA', '')
        self.corex_appdata = os.path.join(self.appdata_path, 'Corex') if self.appdata_path else ''
        self.project_root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
        self.backend_path = os.path.join(self.project_root, 'backend')

    def load_environment(self) -> Dict[str, Any]:
        """Load environment variables from AppData .env file"""
        env_file = os.path.join(self.corex_appdata, '.env')
        env_vars = {
            'APP_ENV': 'local',
            'APP_DEBUG': 'true',
            'CACHE_DRIVER': 'redis',
            'SESSION_DRIVER': 'redis',
            'QUEUE_CONNECTION': 'redis',
            'DB_CONNECTION': 'sqlite',
            'REDIS_HOST': '127.0.0.1',
            'REDIS_PORT': '6379',
            'REDIS_CLIENT': 'phpredis',
            'LOG_CHANNEL': 'stack',
            'LOG_PATH': os.path.join(self.corex_appdata, 'storage', 'logs'),
            'WINDOWS_APP_DATA_PATH': self.corex_appdata,
            'WINDOWS_STORAGE_DIR': os.path.join(self.corex_appdata, 'storage'),
            'WINDOWS_QUEUE_DIR': os.path.join(self.corex_appdata, 'queues'),
        }

        if os.path.exists(env_file):
            logger.info("Loading environment from %s", env_file)
            with open(env_file, 'r', encoding='utf-8') as f:
                for line in f:
                    line = line.strip()
                    if line and not line.startswith('#'):
                        if '=' in line:
                            key, value = line.split('=', 1)
                            env_vars[key.strip()] = value.strip()
        else:
            logger.warning("Environment file not found at %s", env_file)

        return env_vars

    def setup_windows_paths(self) -> bool:
        """Create necessary directory structure in AppData"""
        if not self.corex_appdata:
            logger.error("AppData path not found")
            return False

        try:
            Path(self.corex_appdata).mkdir(parents=True, exist_ok=True)

            subdirectories = [
                'backend',
                'database',
                'storage',
                'storage/logs',
                'cache',
                'queues',
                'backups',
                'local_cache',
            ]

            for subdir in subdirectories:
                Path(os.path.join(self.corex_appdata, subdir)).mkdir(parents=True, exist_ok=True)

            logger.info("Created Corex directory structure in %s", self.corex_appdata)
            return True
        except Exception as e:
            logger.error("Failed to create directory structure: %s", str(e))
            return False

    def initialize_laravel(self, env_vars: Dict[str, Any]) -> bool:
        """Initialize Laravel application"""
        if not os.path.exists(self.backend_path):
            logger.error("Laravel backend not found at %s", self.backend_path)
            return False

        try:
            # Generate application key
            key_command = [sys.executable, 'artisan', 'key:generate']
            result = self.run_command(key_command, self.backend_path)
            if result['returncode'] != 0:
                logger.error("Failed to generate application key")
                return False

            logger.info("Application key generated successfully")

            # Run migrations if database doesn't exist
            db_path = os.path.join(self.corex_appdata, 'database', 'corex.sqlite')
            if not os.path.exists(db_path):
                logger.info("Running migrations...")
                migrate_command = [sys.executable, 'artisan', 'migrate']
                result = self.run_command(migrate_command, self.backend_path, env_vars)
                if result['returncode'] != 0:
                    logger.error("Failed to run migrations")
                    return False

                logger.info("Migrations completed successfully")

            # Create storage link
            logger.info("Creating storage link...")
            storage_command = [sys.executable, 'artisan', 'storage:link']
            result = self.run_command(storage_command, self.backend_path, env_vars)
            if result['returncode'] != 0:
                logger.warning("Storage link creation may have failed")

            return True
        except Exception as e:
            logger.error("Laravel initialization failed: %s", str(e))
            return False

    def create_service_wrapper(self) -> bool:
        """Create service wrapper configuration"""
        try:
            wrapper_path = os.path.join(self.project_root, 'windows-service-wrapper', 'wrapper.py')
            if not os.path.exists(wrapper_path):
                logger.error("Service wrapper not found at %s", wrapper_path)
                return False

            # Set up environment variables for the wrapper
            env_wrapper_vars = {
                'PYTHONPATH': os.path.dirname(os.path.abspath(__file__)),
                'COREX_APP_DATA_PATH': self.corex_appdata,
                'PYTHONUNBUFFERED': '1',
            }

            # Copy wrapper to a location where it can be executed from command line
            commands_dir = os.path.join(self.corex_appdata, 'backend')
            Path(commands_dir).mkdir(parents=True, exist_ok=True)

            wrapper_dest = os.path.join(commands_dir, 'windows_service_wrapper.py')
            with open(wrapper_path, 'r', encoding='utf-8') as src, open(wrapper_dest, 'w', encoding='utf-8') as dst:
                dst.write(src.read())

            logger.info("Service wrapper created at %s", wrapper_dest)
            return True
        except Exception as e:
            logger.error("Failed to create service wrapper: %s", str(e))
            return False

    def run_laravel_server(self, env_vars: Dict[str, Any]) -> bool:
        """Run the Laravel development server"""
        if not os.path.exists(self.backend_path):
            logger.error("Cannot run server - backend path not found")
            return False

        try:
            # Set up environment
            full_env = os.environ.copy()
            full_env.update(env_vars)

            # Use the service wrapper if available
            wrapper_path = os.path.join(self.backend_path, 'windows_service_wrapper.py')
            if os.path.exists(wrapper_path):
                python_path = sys.executable
                command = [python_path, wrapper_path]
            else:
                # Fallback to direct Laravel execution
                php_path = 'php' if platform.system() == 'Windows' else 'php'
                command = [php_path, 'artisan', 'serve', '--host=0.0.0.0', '--port=8000']

            logger.info("Starting Laravel server with command: %s", ' '.join(command))

            # Run the server
            process = subprocess.Popen(
                command,
                cwd=self.backend_path,
                env=full_env,
                stdout=subprocess.PIPE,
                stderr=subprocess.STDOUT,
                universal_newlines=True,
                bufsize=1
            )

            # Log output
            for line in process.stdout:
                if line:
                    line = line.strip()
                    logger.info("Laravel: %s", line)

            process.wait()
            return process.returncode == 0
        except Exception as e:
            logger.error("Failed to run Laravel server: %s", str(e))
            return False

    def run_command(self, command: list, cwd: str, env_vars: Dict[str, Any] = None) -> Dict[str, Any]:
        """Run a command and capture output"""
        if env_vars is None:
            env_vars = {}

        full_env = os.environ.copy()
        full_env.update(env_vars)

        try:
            process = subprocess.Popen(
                command,
                cwd=cwd,
                env=full_env,
                stdout=subprocess.PIPE,
                stderr=subprocess.STDOUT,
                universal_newlines=True
            )

            output = []
            for line in process.stdout:
                if line:
                    output.append(line.strip())

            process.wait()
            return {
                'returncode': process.returncode,
                'output': output
            }
        except Exception as e:
            logger.error("Command failed: %s", str(e))
            return {
                'returncode': 1,
                'output': [str(e)]
            }

    def setup_scheduled_tasks(self) -> bool:
        """Set up Windows scheduled tasks for Laravel scheduler"""
        try:
            # This is a basic implementation - in production, you might want to use
            # the PowerShell script approach or Task Scheduler API
            tasks_dir = os.path.join(self.corex_appdata, 'tasks')
            Path(tasks_dir).mkdir(parents=True, exist_ok=True)

            # Create a simple task script
            task_script = os.path.join(tasks_dir, 'scheduler_task.ps1')
            with open(task_script, 'w', encoding='utf-8') as f:
                f.write(f'''# Corex.dev Scheduler Task
# This script is executed by Windows Task Scheduler

$ErrorActionPreference = 'Stop'

$backendPath = '{self.backend_path}'
$env:PATH = "$backendPath;$env:PATH"
$env:APP_ENV = 'local'

cd $backendPath

Write-Host "Running scheduled tasks..."
php artisan schedule:run --env=local
''')

            logger.info("Scheduler task script created at %s", task_script)
            return True
        except Exception as e:
            logger.error("Failed to create scheduled tasks: %s", str(e))
            return False

    def install_dependencies(self) -> bool:
        """Check and install required dependencies"""
        checks = [
            ('PHP', self.check_php_installation),
            ('Composer', self.check_composer_installation),
            ('Redis', self.check_redis_installation),
        ]

        for name, check_func in checks:
            if not check_func(name):
                logger.warning("Required dependency '%s' not found", name)

        return True

    def check_php_installation(self, dependency_name: str) -> bool:
        """Check if PHP is installed"""
        try:
            php_path = 'php' if platform.system() == 'Windows' else '/usr/bin/php'
            result = self.run_command([php_path, '--version'], '.')
            if result['returncode'] == 0:
                logger.info("PHP found at: %s", php_path)
                return True
            return False
        except Exception:
            logger.error("Failed to check PHP installation")
            return False

    def check_composer_installation(self, dependency_name: str) -> bool:
        """Check if Composer is installed"""
        try:
            composer_path = 'composer' if platform.system() == 'Windows' else '/usr/bin/composer'
            result = self.run_command([composer_path, '--version'], '.')
            if result['returncode'] == 0:
                logger.info("Composer found at: %s", composer_path)
                return True
            return False
        except Exception:
            logger.error("Failed to check Composer installation")
            return False

    def check_redis_installation(self, dependency_name: str) -> bool:
        """Check if Redis is installed"""
        try:
            redis_path = 'redis-server' if platform.system() == 'Windows' else '/usr/bin/redis-server'
            result = self.run_command([redis_path, '--version'], '.')
            if result['returncode'] == 0:
                logger.info("Redis found at: %s", redis_path)
                return True
            return False
        except Exception:
            logger.error("Failed to check Redis installation")
            return False

    def print_status(self) -> None:
        """Print service status information"""
        print("\n=== Corex.dev Windows Service Status ===\n")

        # Check AppData structure
        if self.corex_appdata and os.path.exists(self.corex_appdata):
            print(f"AppData Path: {self.corex_appdata}")

            # List directories
            for item in os.listdir(self.corex_appdata):
                full_path = os.path.join(self.corex_appdata, item)
                if os.path.isdir(full_path):
                    print(f"  • {item}/")
                else:
                    size = os.path.getsize(full_path)
                    print(f"  • {item} ({size} bytes)")

            # Check for database
            db_path = os.path.join(self.corex_appdata, 'database', 'corex.sqlite')
            if os.path.exists(db_path):
                print(f"  ✓ Database found: {db_path}")
            else:
                print(f"  ✗ Database not found: {db_path}")
        else:
            print("AppData not configured")

        # Check backend
        print(f"\nBackend Path: {self.backend_path}")
        if os.path.exists(self.backend_path):
            print("  ✓ Backend found")
        else:
            print("  ✗ Backend not found")

        # Print environment variables
        env_vars = self.load_environment()
        print("\nEnvironment Variables:")
        for key, value in env_vars.items():
            if key.startswith('REDIS_'):
                print(f"  {key}=***")
            else:
                print(f"  {key}={value}")

    def initialize_service(self) -> bool:
        """Initialize the Corex service"""
        logger.info("Initializing Corex Windows service...")

        # Setup Windows paths
        if not self.setup_windows_paths():
            logger.error("Failed to setup Windows paths")
            return False

        # Load environment variables
        env_vars = self.load_environment()

        # Initialize Laravel
        if not self.initialize_laravel(env_vars):
            logger.error("Failed to initialize Laravel")
            return False

        # Create service wrapper
        if not self.create_service_wrapper():
            logger.error("Failed to create service wrapper")
            return False

        # Check and install dependencies
        self.install_dependencies()

        # Setup scheduled tasks
        if not self.setup_scheduled_tasks():
            logger.warning("Failed to setup scheduled tasks")

        logger.info("Service initialization completed")
        return True

    def stop_service(self) -> bool:
        """Stop the Corex service"""
        logger.info("Stopping Corex service...")
        # In a real implementation, this would stop the Windows service
        # For now, it just logs the action
        return True

    def start_service(self) -> bool:
        """Start the Corex service"""
        logger.info("Starting Corex service...")

        # Load environment variables
        env_vars = self.load_environment()

        # Initialize Laravel if needed
        self.setup_windows_paths()
        self.initialize_laravel(env_vars)

        # Run the Laravel server
        logger.info("Starting Laravel development server...")
        return self.run_laravel_server(env_vars)
def parse_arguments():
    """Parse command line arguments"""
    parser = argparse.ArgumentParser(
        description='Corex.dev Windows Service Manager'
    )

    subparsers = parser.add_subparsers(dest='command', help='Command to execute')

    # Initialize command
    parser_init = subparsers.add_parser('initialize', help='Initialize the service')
    parser_init.add_argument('--force', action='store_true', help='Force re-initialization')

    # Start command
    parser_start = subparsers.add_parser('start', help='Start the service')

    # Stop command
    parser_stop = subparsers.add_parser('stop', help='Stop the service')

    # Status command
    parser_status = subparsers.add_parser('status', help='Show service status')

    # Setup tasks command
    parser_setup_tasks = subparsers.add_parser('setup-tasks', help='Set up scheduled tasks')

    return parser.parse_args()
def main():
    """Main entry point"""
    args = parse_arguments()

    manager = CorexWindowsServiceManager()

    try:
        if args.command == 'initialize':
            if manager.initialize_service():
                print("\n✓ Service initialized successfully!")
                return 0
            else:
                print("\n✗ Failed to initialize service")
                return 1

        elif args.command == 'start':
            print("Starting Corex.dev Windows service...")
            manager.print_status()

            print("\nStarting Laravel development server...")
            if manager.start_service():
                print("\n✓ Service started successfully!")
                return 0
            else:
                print("\n✗ Failed to start service")
                return 1

        elif args.command == 'stop':
            print("Stopping Corex.dev Windows service...")
            if manager.stop_service():
                print("\n✓ Service stopped successfully!")
                return 0
            else:
                print("\n✗ Failed to stop service")
                return 1

        elif args.command == 'status':
            manager.print_status()
            return 0

        elif args.command == 'setup-tasks':
            print("Setting up scheduled tasks...")
            if manager.setup_scheduled_tasks():
                print("\n✓ Scheduled tasks set up successfully!")
                return 0
            else:
                print("\n✗ Failed to set up scheduled tasks")
                return 1

        else:
            parser.print_help()
            return 1

    except KeyboardInterrupt:
        print("\nOperation interrupted by user")
        return 130
    except Exception as e:
        print(f"\n✗ Error: {str(e)}")
        logger.exception("Unhandled exception")
        return 1
if __name__ == '__main__':
    import subprocess
    sys.exit(main())