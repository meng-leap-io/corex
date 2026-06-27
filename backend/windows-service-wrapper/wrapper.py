#!/usr/bin/env python3
"""
Windows Service Wrapper for Corex.dev Laravel Application

This wrapper allows the Laravel application to run as a Windows service.
It also handles Windows service lifecycle, logging, and process management.
"""

import sys
import os
import logging
import signal
import subprocess
import time
import win32service
import win32serviceutil
import servicemanager
from win32event import WaitForSingleObject, INFINITE

# Add the current directory to Python path so we can import from the same directory
import importlib.util
spec = importlib.util.spec_from_file_location("corex_settings", os.path.join(os.path.dirname(__file__), "windows_config.py"))
corex_settings = importlib.util.module_from_spec(spec)
spec.loader.exec_module(corex_settings)

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler(os.path.join(os.environ.get('APPDATA', ''), 'Corex', 'logs', 'service.log')),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger('corex-service')

class CorexWindowsService(win32service.ServiceFramework):
    _svc_name_ = 'CorexLaravelService'
    _svc_display_name_ = 'Corex.dev Laravel Application'
    _svc_description_ = 'Corex.dev Laravel PHP application running on Windows as a service'

    def __init__(self, args):
        win32service.ServiceFramework.__init__(self, args)
        servicemanager.LogMsg(
            servicemanager.EVENTLOG_INFORMATION,
            0,
            self._svc_name_,
            "%s service initialized" % self._svc_display_name_
        )
        self.php_process = None
        self.shutdown_event = None

    def SvcStop(self):
        """Service stop request"""
        self.ReportServiceStatus(win32service.SERVICE_STOP_PENDING)
        logger.info("Shutting down Corex service...")
        servicemanager.LogMsg(
            servicemanager.EVENTLOG_INFORMATION,
            0,
            self._svc_name_,
            "Stopping Corex service"
        )

        self.shutdown_event = win32event.CreateEvent(None, 1, 1, None)

        if self.php_process:
            logger.info("Terminating PHP process...")
            self.php_process.terminate()
            try:
                self.php_process.wait(timeout=30)
            except subprocess.TimeoutExpired:
                logger.warning("Process did not terminate gracefully, forcing kill")
                self.php_process.kill()

        if self.shutdown_event:
            WaitForSingleObject(self.shutdown_event, INFINITE)

        win32serviceutil.ReportServiceStatus(self, win32service.SERVICE_STOPPED)
        logger.info("Corex service stopped")
        servicemanager.LogMsg(
            servicemanager.EVENTLOG_INFORMATION,
            0,
            self._svc_name_,
            "Corex service stopped"
        )

    def SvcPause(self):
        """Service pause request"""
        logger.info("Corex service paused")
        self.ReportServiceStatus(win32service.SERVICE_PAUSED)

    def SvcContinue(self):
        """Service continue request"""
        logger.info("Corex service continuing")
        self.ReportServiceStatus(win32service.SERVICE_RUNNING)

    def SvcDoRun(self):
        """Service main entry point"""
        servicemanager.LogMsg(
            servicemanager.EVENTLOG_INFORMATION,
            0,
            self._svc_name_,
            "%s service started" % self._svc_display_name_
        )
        logger.info("Starting Corex service...")
        self.ReportServiceStatus(win32service.SERVICE_START_PENDING)

        try:
            self.ReportServiceStatus(win32service.SERVICE_RUNNING)

            # Wait for PHP process to complete or shutdown signal
            while True:
                if self.php_process:
                    ret_code = self.php_process.poll()
                    if ret_code is not None:
                        logger.error("PHP process terminated with code: %d", ret_code)
                        break
                time.sleep(1)

        except Exception as e:
            logger.error("Service error: %s", str(e))
            servicemanager.LogMsg(
                servicemanager.EVENTLOG_ERROR,
                0,
                self._svc_name_,
                "Service error: %s" % str(e)
            )

        self.ReportServiceStatus(win32service.SERVICE_STOPPED)
        logger.info("Service stopped")

    def start_laravel(self):
        """Start the Laravel application"""
        logger.info("Starting Laravel application...")

        # Determine the project root directory
        app_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

        # Check if the Laravel project exists
        artisan_path = os.path.join(app_dir, "backend", "artisan")
        if not os.path.exists(artisan_path):
            logger.error("Laravel application not found at %s", artisan_path)
            raise FileNotFoundError(f"Laravel application not found at {artisan_path}")

        # Set up environment variables
        env = os.environ.copy()
        env.update({
            'APP_ENV': 'local',
            'APP_DEBUG': 'true',
            'CACHE_DRIVER': 'redis',
            'SESSION_DRIVER': 'redis',
            'QUEUE_CONNECTION': 'redis',
            'DB_CONNECTION': 'sqlite',
        })

        # Set Windows AppData paths from settings
        appdata_path = os.environ.get('APPDATA', '')
        env.update({
            'APP_STORAGE_PATH': os.path.join(appdata_path, 'Corex', 'storage'),
            'APP_PUBLIC_PATH': os.path.join(appdata_path, 'Corex', 'public'),
            'DB_DATABASE': os.path.join(appdata_path, 'Corex', 'database', 'corex.sqlite'),
            'LOG_PATH': os.path.join(appdata_path, 'Corex', 'storage', 'logs'),
            'WINDOWS_APP_DATA_PATH': os.path.join(appdata_path, 'Corex'),
        })

        # Determine PHP executable
        php_executable = sys.executable if sys.platform == "win32" else "php"

        # Start the Laravel development server
        command = [php_executable, "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

        try:
            self.php_process = subprocess.Popen(
                command,
                cwd=os.path.join(app_dir, "backend"),
                env=env,
                stdout=subprocess.PIPE,
                stderr=subprocess.STDOUT,
                universal_newlines=True,
                bufsize=1
            )

            # Stream output to log and service manager
            for line in self.php_process.stdout:
                if line:
                    line = line.strip()
                    logger.info("Laravel stdout: %s", line)
                    servicemanager.LogMsg(
                        servicemanager.EVENTLOG_INFORMATION,
                        0,
                        self._svc_name_,
                        line[:1024]
                    )

        except Exception as e:
            logger.error("Failed to start Laravel: %s", str(e))
            raise

# Handle import errors for Windows-specific modules
if sys.platform == "win32":
    import win32event
    import servicemanager
    import win32api
else:
    def dummy():
        pass
    win32event = dummy()
    servicemanager = dummy()
    win32api = dummy()

if __name__ == '__main__':
    if len(sys.argv) == 1:
        servicemanager.Initialize()
        servicemanager.PrepareToHostSingle(CorexWindowsService)
        win32serviceutil.Run()
    else:
        win32serviceutil.HandleCommandLine(CorexWindowsService)