# Contributing to Modular Router

Thank you for considering contributing to the Modular Router! This guide will help you get started with development.

## Development Setup

### Prerequisites

- PHP 8.4+
- Composer
- Docker (optional, for devcontainer)

### Setup

1. **Clone the repository**:
   ```bash
   git clone https://github.com/power-modules/router.git
   cd router
   ```

2. **Install dependencies**:
   ```bash
   composer install
   ```

3. **Optional: Use devcontainer**:
   ```bash
   make devcontainer
   ```

## Development Workflow

### Code Quality

We maintain high code quality standards:

```bash
make test         # Run PHPUnit tests
make codestyle    # Check PHP CS Fixer compliance
make phpstan      # Run PHPStan static analysis
```

### Testing

- **Unit tests**: Located in `test/Unit/`
- **Functional tests**: Located in `test/`
- **Test coverage**: Use `#[CoversClass(ClassName::class)]` attribute pattern
- **Sample modules**: Available in `test/Unit/Sample/` for demonstration

### Code Standards

- **Strict types**: All files must use `declare(strict_types=1);`
- **PSR-4 autoloading**: `Modular\Router\` → `src/`
- **Type safety**: Use enum-based patterns (e.g., `RouteMethod::Get`)
- **Interface contracts**: Prefer interfaces over concrete dependencies

## Submitting Changes

### Pull Request Process

1. **Fork the repository**
2. **Create a feature branch**:
   ```bash
   git checkout -b feature/your-feature-name
   ```
3. **Make your changes** following the code standards
4. **Add tests** for new functionality
5. **Run quality checks**:
   ```bash
   make test codestyle phpstan
   ```
6. **Commit with conventional format**:
   ```bash
   git commit -m "feat(router): add new feature"
   ```
7. **Push and create pull request**

### Commit Message Format

Use [Conventional Commits](https://www.conventionalcommits.org/):

- `feat(scope): add new feature`
- `fix(scope): fix bug`
- `docs(scope): update documentation`
- `refactor(scope): refactor code`
- `test(scope): add tests`

## Documentation

### API Documentation

- Document all public interfaces
- Include code examples
- Follow existing patterns in the codebase

### Examples

- Provide real-world usage examples
- Test examples to ensure they work
- Keep examples up-to-date with API changes

## Questions and Support

- **Issues**: Use GitHub Issues for bug reports and feature requests
- **Discussions**: Use GitHub Discussions for questions and ideas
- **Framework**: See [power-modules/framework](https://github.com/power-modules/framework) for framework-level questions

## License

By contributing, you agree that your contributions will be licensed under the MIT License.