# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-02-20

### Added
- Initial release
- Email validation via `validate()` method
- Form-based validation via `formValidate()` method
- Account info via `account()` method
- Retry with exponential backoff on 429/5xx errors
- Configurable error handling (`raise_on_error`)
- Result predicates: `isValid()`, `isInvalid()`, `isRisky()`, `isUnknown()`, `isError()`
- Email attribute predicates: `isFreeEmail()`, `isRole()`, `isDisposable()`
- `allowRisky` parameter on `isValid()` for flexible validation
