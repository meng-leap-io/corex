export enum LogLevel {
  DEBUG = 'debug',
  INFO = 'info',
  WARN = 'warn',
  ERROR = 'error',
}

export interface LogEntry {
  level: LogLevel
  message: string
  function: string
  timestamp: string
  requestId?: string
  duration?: number
  error?: Record<string, unknown>
  metadata?: Record<string, unknown>
}

export class Logger {
  private functionName: string
  private requestId: string | null

  constructor(functionName: string, requestId?: string) {
    this.functionName = functionName
    this.requestId = requestId ?? crypto.randomUUID()
  }

  debug(message: string, metadata?: Record<string, unknown>): void {
    this.log(LogLevel.DEBUG, message, metadata)
  }

  info(message: string, metadata?: Record<string, unknown>): void {
    this.log(LogLevel.INFO, message, metadata)
  }

  warn(message: string, metadata?: Record<string, unknown>): void {
    this.log(LogLevel.WARN, message, metadata)
  }

  error(message: string, error?: Error | Record<string, unknown>): void {
    const errorData: Record<string, unknown> = {}

    if (error instanceof Error) {
      errorData.message = error.message
      errorData.stack = error.stack
      errorData.name = error.name
    } else if (error) {
      Object.assign(errorData, error)
    }

    this.log(LogLevel.ERROR, message, errorData)
  }

  startTimer(): () => number {
    const start = performance.now()
    return () => performance.now() - start
  }

  private log(
    level: LogLevel,
    message: string,
    metadata?: Record<string, unknown>,
  ): void {
    const entry: LogEntry = {
      level,
      message,
      function: this.functionName,
      timestamp: new Date().toISOString(),
      requestId: this.requestId ?? undefined,
      metadata,
    }

    const line = JSON.stringify(entry)

    switch (level) {
      case LogLevel.ERROR:
        console.error(line)
        break
      case LogLevel.WARN:
        console.warn(line)
        break
      default:
        console.log(line)
    }
  }
}
