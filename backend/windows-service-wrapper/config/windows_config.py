"""
Windows-specific configuration for Corex.dev Laravel application.
This module provides access to Windows-specific settings and utilities.
"""

from pathlib import Path
from typing import Dict, Any, Optional
import os
from dotenv import dotenv_values
class WindowsConfig:
    """Windows-specific configuration settings"""

    @staticmethod
    def get_appdata_dir() -> str:
        """Get the Corex application directory in AppData"""
        from os import environ
        userprofile = environ.get('USERPROFILE', '')
        if not userprofile:
            return 'C:\\Users\\User\\AppData\\Local\\Corex'

        appdata_path = os.path.join(userprofile, 'AppData', 'Local', 'Corex')
        return appdata_path.replace('/', os.sep)

    @staticmethod
    def get_corex_home() -> str:
        """Get the Corex home directory"""
        return WindowsConfig.get_appdata_dir()

    @staticmethod
    def get_storage_path() -> str:
        """Get the Corex storage path"""
        return os.path.join(WindowsConfig.get_corex_home(), 'storage').replace('/', os.sep)

    @staticmethod
    def get_database_path() -> str:
        """Get the SQLite database path"""
        return os.path.join(WindowsConfig.get_corex_home(), 'database', 'corex.sqlite').replace('/', os.sep)

    @staticmethod
    def get_logs_path() -> str:
        """Get the logs path"""
        return os.path.join(WindowsConfig.get_storage_path(), 'logs').replace('/', os.sep)

    @staticmethod
    def get_cache_path() -> str:
        """Get the cache path"""
        return os.path.join(WindowsConfig.get_corex_home(), 'cache').replace('/', os.sep)

    @staticmethod
    def get_queue_path() -> str:
        """Get the queue path"""
        return os.path.join(WindowsConfig.get_corex_home(), 'queues').replace('/', os.sep)

    @staticmethod
    def get_backups_path() -> str:
        """Get the backups path"""
        return os.path.join(WindowsConfig.get_corex_home(), 'backups').replace('/', os.sep)

    @staticmethod
    def get_local_cache_path() -> str:
        """Get the local cache path"""
        return os.path.join(WindowsConfig.get_corex_home(), 'local_cache').replace('/', os.sep)

    @staticmethod
    def create_directory_structure() -> None:
        """Create the complete directory structure"""
        home = WindowsConfig.get_corex_home()
        directories = [
            'backend',
            'database',
            'storage',
            'storage/logs',
            'storage/framework',
            'cache',
            'queues',
            'backups',
            'local_cache',
        ]

        for directory in directories:
            dir_path = os.path.join(home, directory)
            Path(dir_path).mkdir(parents=True, exist_ok=True)

    @staticmethod
    def load_env_file() -> Dict[str, str]:
        """Load environment variables from .env file"""
        env_file = os.path.join(WindowsConfig.get_corex_home(), '.env')
        if os.path.exists(env_file):
            return dotenv_values(env_file)
        return {}

    @staticmethod
    def save_env_file(env_vars: Dict[str, Any]) -> bool:
        """Save environment variables to .env file"""
        try:
            env_file = os.path.join(WindowsConfig.get_corex_home(), '.env')
            with open(env_file, 'w', encoding='utf-8') as f:
                for key, value in env_vars.items():
                    f.write(f'{key}={value}\n')
            return True
        except Exception as e:
            print(f"Error saving .env file: {e}")
            return False

    @staticmethod
    def get_default_env_vars() -> Dict[str, Any]:
        """Get default environment variables for Windows deployment"""
        home = WindowsConfig.get_corex_home()

        return {
            'APP_NAME': 'Corex',
            'APP_ENV': 'local',
            'APP_DEBUG': 'true',
            'APP_STORAGE_PATH': WindowsConfig.get_storage_path(),
            'APP_PUBLIC_PATH': os.path.join(home, 'public').replace('/', os.sep),
            'DB_CONNECTION': 'sqlite',
            'DB_DATABASE': WindowsConfig.get_database_path(),
            'DB_FOREIGN_KEYS': 'false',
            'CACHE_DRIVER': 'redis',
            'SESSION_DRIVER': 'redis',
            'QUEUE_CONNECTION': 'redis',
            'REDIS_HOST': '127.0.0.1',
            'REDIS_PORT': '6379',
            'REDIS_CLIENT': 'phpredis',
            'REDIS_ASYNC_FLUSH': 'true',
            'LOG_CHANNEL': 'stack',
            'LOG_PATH': WindowsConfig.get_logs_path(),
            'WINDOWS_APP_DATA_PATH': WindowsConfig.get_corex_home(),
            'WINDOWS_STORAGE_DIR': WindowsConfig.get_storage_path(),
            'WINDOWS_QUEUE_DIR': WindowsConfig.get_queue_path(),
            'CACHE_ENABLE_MEMORY_CACHE': 'true',
            'CACHE_LOCAL_STORAGE_PATH': WindowsConfig.get_local_cache_path(),
            'CACHE_MAX_LOCAL_MB': '100',
            'AI_GATEWAY_ENABLE_OFFLINE_MODE': 'true',
            'AI_GATEWAY_OFFLINE_MAX_TOKENS': '100000',
            'DB_BACKUP_ENABLED': 'true',
            'DB_BACKUP_PATH': WindowsConfig.get_backups_path(),
            'DB_BACKUP_RETENTION_DAYS': '30',
        }

    @staticmethod
    def validate_config() -> bool:
        """Validate Windows-specific configuration"""
        home = WindowsConfig.get_corex_home()

        if not os.path.exists(home):
            print(f"Warning: Corex home directory does not exist: {home}")
            return False

        required_dirs = ['storage', 'database']
        for dir_name in required_dirs:
            dir_path = os.path.join(home, dir_name)
            if not os.path.exists(dir_path):
                print(f"Warning: Required directory does not exist: {dir_path}")
                return False

        return True

    @staticmethod
    def get_system_info() -> Dict[str, str]:
        """Get system information for debugging"""
        import platform

        return {
            'platform': platform.system(),
            'platform_version': platform.version(),
            'python_version': platform.python_version(),
            'architecture': platform.architecture()[0],
            'processor': platform.processor() or 'Unknown',
            'home_dir': WindowsConfig.get_corex_home(),
            'database_path': WindowsConfig.get_database_path(),
            'storage_path': WindowsConfig.get_storage_path(),
        }