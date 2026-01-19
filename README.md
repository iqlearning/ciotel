# iqtool/ciotel

OpenTelemetry Integration for CodeIgniter 4.

## Description

This package provides seamless integration of OpenTelemetry with CodeIgniter 4 applications. It enables automatic tracing of incoming requests, database queries, and integrates with the CodeIgniter logging system.

## Features

- **Automatic Request Tracing**: Automatically instrument incoming HTTP requests using `OtelFilter`.
- **Database Query Tracing**: Automatically trace database queries including SQL statements and execution time using `OtelDbListener`.
- **Log Integration**: Pushes CodeIgniter logs (`log_message()`) to OpenTelemetry via `OtelHandler`.
- **Zero Configuration**: Uses CodeIgniter 4's `Registrar` for auto-discovery of events, filters, and services.

## Installation

Install the package via Composer:

```bash
composer require iqtool/ciotel
```

## Configuration

The package is designed to work out-of-the-box. It uses standard OpenTelemetry environment variables for configuration (e.g., `OTEL_EXPORTER_OTLP_ENDPOINT`, `OTEL_SERVICE_NAME`).

### Database Tracing

Database tracing spans will automatically include:

- `db.system`: Trace driver (e.g. mysql, sqlite).
- `db.statement`: The executed SQL query.
- `db.name`: Database name.
- `db.hostname`: Database host.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
