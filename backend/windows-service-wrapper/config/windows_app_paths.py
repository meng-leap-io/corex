"""
Configuration for Corex.dev Windows Desktop Application

This module contains Windows-specific configuration for paths, settings,
and environment variables that are specific to the Windows desktop deployment.
"""

from __future__ import annotations

from pathlib import Path
from typing import Dict, Any
class WindowsAppPaths:
    """Manage Windows-specific paths for the application"""

    @staticmethod
    def get_user_profile() -> str:
        """Get the user's profile directory with Windows path separator"""
        from os import getenv
        userprofile = getenv('USERPROFILE') or getenv('HOMEDRIVE') + getenv('HOMEPATH', '')
        return userprofile.replace('/', '\\') if userprofile else 'C:\\Users\\User'

    @staticmethod
    def get_appdata_dir(app_name: str = 'Corex') -> str:
        """Get the AppData directory for the application"""
        userprofile = WindowsAppPaths.get_user_profile()
        return f"{userprofile}\\AppData\\Local\\{app_name}"

    @staticmethod
    def create_appdata_structure(appdata_path: str) -> Dict[str, str]:
        """Create the application directory structure in AppData"""
        directories = {
            'database': f"{appdata_path}\\database",
            'storage': f"{appdata_path}\\storage",
            'storage_logs': f"{appdata_path}\\storage\\logs",
            'cache': f"{appdata_path}\\cache",
            'queues': f"{appdata_path}\\queues",
            'backups': f"{appdata_path}\\backups",
            'local_cache': f"{appdata_path}\\local_cache",
        }

        for dir_path in directories.values():
            Path(dir_path).mkdir(parents=True, exist_ok=True)

        return directories

    @staticmethod
    def get_sqlite_database_path(appdata_path: str) -> str:
        """Get the SQLite database file path"""
        return f"{appdata_path}\\database\\corex.sqlite"

    @staticmethod
    def get_log_file_path(appdata_path: str, log_type: str = 'corex.log') -> str:
        """Get the log file path"""
        return f"{appdata_path}\\storage\\logs\\{log_type}""

    @staticmethod
    def get_env_file_path(appdata_path: str) -> str:
        """Get the .env file path"""
        return f"{appdata_path}\\backend\\.env"