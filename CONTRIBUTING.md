# Project Code Norms

Norms of this bundle. They derive from the geonative-api norms that apply to a small Symfony bundle with a Doctrine entity, a console command, a couple of services and a Pest suite on SQLite; the API-only rules (API Platform, messenger, migrations, protocols, spatial types) are left out on purpose.

## [test-conventions]

Applies to `tests/`. The test app lives under `tests/App/` (kernel, entities, repositories, connection doubles) and the shared helpers in `tests/Pest.php`.

### Use the project test helpers, always
- Every test goes through the helpers of `tests/Pest.php` for the things they cover: `container()` for a service, `entityManager($class)` for a manager, `repository($class)` for a repository, `save(...$entities)` for persistence, `create_schema()` in `beforeAll()`
- Raw framework calls are forbidden where a helper exists: no `$em->persist(); $em->flush()`, no `app()->getContainer()`, no `new SchemaTool`, no `self::bootKernel()` in a test file
- A sequence of statements that two tests repeat (a fixture, a precondition, a reading of a spy's SQL, a mock graph) is a helper, never a copy: put it in `tests/Pest.php` when every directory needs it, in a `functions.php` beside the tests when one directory does
- Test doubles (spy or flaky connections, fake registries) are classes under `tests/App/`, one file each, reused by name; never an anonymous class duplicated across tests
- Severity: ERROR

### PestPHP naming
- Use `test()` for test descriptions that don't start with "it"
- Use `it()` only when the description naturally reads as "it does something"
- Severity: WARNING

### Prefer separate expect() calls over ->and() chains
- Write each assertion as its own `expect(...)->...()` statement; do not chain assertions with `->and(...)`
- Rationale: separate statements give clearer failure output (which assertion failed) and read more consistently
- Severity: WARNING

### Use precise domain terminology in test names
- Test descriptions use the exact domain term of this bundle: "prunable repository", "log manager", "entities connection", "check interval", "stale entity"
- Avoid ambiguous or shortened terms that lose specificity
- Severity: WARNING

### Save only the root entity in tests
- When using the `save()` helper, only save the root entity — Doctrine cascade will handle related entities
- Severity: WARNING

### Use Given/When/Then section comments
- Every test structures its body with the bare `// Given`, `// When`, `// Then` section comments
- `// Given` = test setup and preconditions
- `// When` = the action under test
- `// Then` = assertions on the result
- The section comment carries no sentence after the keyword; what the section does is said by the code and, when needed, by a separate single-line comment below it
- Severity: ERROR

### No compound test names with "and"
- Test descriptions focus on a single concern — do not use "and" to combine multiple assertions into one test name
- Split compound tests into separate, focused test cases
- Severity: WARNING

### Use @var for type narrowing in tests
- Use `/** @var Type $var */` annotations (not `assert()`) for type narrowing in tests
- Severity: WARNING

### Use beforeEach for shared test setup
- When multiple tests need the same setup, use `beforeEach()` instead of repeating it in each test body
- Severity: WARNING

### No setup code in global test scope
- Place shared setup logic inside `beforeAll()` or `beforeEach()`, not at the top-level file scope
- Severity: WARNING

### Place uses() after import statements
- In test files, `uses()` calls are placed after all `use` import statements, not before them
- Order: `declare(strict_types=1)` → `namespace` → `use` imports → `uses()` → `beforeEach()`/`beforeAll()` → tests
- Severity: WARNING

### Use functions.php for test-local helpers
- Define test helper functions (factories, builders, assertion helpers, mock builders) in a `functions.php` file within the relevant test directory, loaded with `require_once __DIR__ . '/functions.php';`
- Keep helpers scoped to the test context where they are used; `tests/Pest.php` holds only what every directory needs
- Severity: WARNING

### Use entityManager()->clear() before database assertions
- Call `entityManager()->clear()` (and `entityManager($class)->clear()` for a class on another manager) before querying the database in the `// Then` section of integration tests
- This clears Doctrine's identity map and forces a fresh database read
- Severity: WARNING

### Use fixed past dates in tests
- Use fixed past dates (e.g. `2024-01-15`) instead of far-future dates in test data
- Exception: a date whose meaning is relative to now by design (a stale entity older than the prune threshold, a check older than the check interval) is written as the relative expression that names that meaning (`new DateTimeImmutable('-1 year')`), never as a computed absolute date
- Severity: WARNING

### Use realistic domain data in tests
- Test data uses realistic values, not obviously synthetic placeholders
- Severity: WARNING

### Parametric tests for sibling variants of the same concept
- When multiple variants of the same concept must be tested with identical assertions, use `->with(...)` parametric datasets instead of duplicating the test body
- Severity: INFO

### A repair lands with the test that fails when it is reverted
- A change in `src/` that fixes a behaviour comes with a test that is red on the previous code; a test that stays green with the change reverted proves nothing
- Severity: ERROR

## [type-strictness]

Applies to services, value objects and commands. Doctrine-mapped entity properties are exempt where the framework requires otherwise (Doctrine cannot hydrate `readonly` mapped properties).

### Minimize attribute visibility
- Prefer `private` or `protected` over `public` by default
- A public attribute is allowed only when the framework requires it: the `public Ulid $id;` identifier and Doctrine-mapped properties
- Severity: WARNING

### Minimize type unions
- Constructor parameters use the narrowest possible type; if a value can be null, declare it `?Type`
- Severity: WARNING

### Use strict comparison operators
- Always use `===` and `!==` instead of `==` and `!=`
- Severity: ERROR

### Yoda conditions
- Place the value on the left side of comparisons: `null === $expr`, `false === $expr`, `true === $expr`
- Severity: WARNING

### Prefer readonly promoted properties
- Use `private readonly Type $prop` as promoted constructor parameters, except where the value is set outside the constructor or the property is a Doctrine-mapped entity property
- Severity: INFO

### Prefer empty collections over null
- Methods that return collections return an empty array (`[]`) instead of `null` when there are no results
- Severity: WARNING

### Services must be final
- All service classes are declared `final`
- Severity: WARNING

### Enums must be string-backed
- All enums use `string` as their backing type
- Severity: WARNING

## [naming]

Applies to identifiers in `src/` and `tests/`. Trivial closure parameters are exempt from the descriptive-name rule.

### Descriptive parameter names
- Use descriptive names like `$lastCheckedAt`, `$entitiesConnection` instead of generic `$date`, `$connection` when two of them coexist
- Severity: WARNING

### No abbreviations in names
- Variable, constant, parameter and method names do not use abbreviations (`$entityManager` not `$em`, `$repository` not `$repo`)
- Severity: WARNING

### Boolean names describe behavior
- Boolean variables and parameters describe the behavior they control (`$failNextQuery`, `$shouldPerformCheck`)
- Severity: WARNING

### American English spelling for identifiers
- Use American spelling for identifiers (`canceled`, not `cancelled`)
- Severity: WARNING

### Named parameters follow constructor declaration order
- When invoking a constructor or method with PHP named arguments, list them in the declaration order
- Severity: WARNING

### Interfaces carry the Interface suffix
- Suffix every interface declaration with `Interface` (`PrunableRepositoryInterface`)
- Severity: WARNING

## [code-hygiene]

Applies to production code under `src/`. Test assertion literals and realistic fixture data are exempt from the magic-value rule.

### Extract variables for repeated expressions
- When an expression is used more than once, extract it into a named local variable
- Severity: WARNING

### No magic values
- Do not use hardcoded literal values with domain meaning — extract them into named constants
- Severity: WARNING

### No inline prose comments in src/
- `src/` carries no inline `//` prose comment; a class docblock says why the class exists when the name cannot, and that is the only comment
- A guard or a branch whose reason is not self-evident is explained by its name (a private method, a named variable), not by a comment
- Severity: WARNING

### Remove unused code
- Remove unused imports, parameters, variables and methods
- Severity: WARNING

### Omit default null values
- Do not explicitly set `null` as default when it is already the parameter's default value
- Severity: WARNING

### No FQCN in code
- Fully qualified class names are forbidden in code — always use `use` imports; FQCN are only allowed in comments and docstrings
- Severity: ERROR

### No production methods for tests only
- Do not add methods to shipped classes solely for test purposes; test-support doubles live under `tests/App/`
- Severity: WARNING

### Import functions explicitly
- Use `use function` to import PHP functions (`use function count;`) instead of relying on the global fallback
- Severity: WARNING

### Use PHPStan error identifiers
- Use `@phpstan-ignore identifier` instead of the deprecated `@phpstan-ignore-next-line` / `@phpstan-ignore-line`
- Severity: WARNING

### Use constructor property promotion
- Always use constructor property promotion instead of declaring properties separately and assigning them in the constructor body
- Severity: ERROR

### Use PHP attributes for Symfony configuration
- Use PHP 8 attributes (`#[AsCommand]`, …) for service configuration; the interface-level auto-tagging of prunable repositories through `_instanceof` in the consumer's services file is the sanctioned exception
- Severity: WARNING

### Use match() expressions instead of switch
- Severity: WARNING

### Use heredoc for multiline structured strings
- Multiline SQL, JSON, YAML in code and tests use heredoc/nowdoc syntax whose tag names the format
- Severity: WARNING

### Use PHP date format constants
- Use `DateTimeInterface::ATOM` and siblings instead of raw format strings
- Severity: WARNING

### No dead methods masked by @codeCoverageIgnore
- A method never invoked is removed, not masked
- Severity: WARNING

### Docblock array shapes use string[]
- Element-typed arrays are documented as `Type[]` (`string[]`, `GarbageCollectorLog[]`), the style of `GarbageCollector::$classes`, not `list<Type>` nor `array<Type>`
- Severity: WARNING

## [entity-conventions]

### Ulid ID block for entity primary keys
- Entities declare their identifier with the four-attribute block `ORM\Id`, `ORM\Column(type: 'ulid', unique: true)`, `ORM\GeneratedValue(strategy: 'CUSTOM')`, `ORM\CustomIdGenerator(class: UlidGenerator::class)` on a `public Ulid $id;`
- Reference: `src/Entity/GarbageCollectorLog.php`
- Severity: WARNING

### Datetime columns use DATETIMETZ_IMMUTABLE
- Date columns are `datetimetz_immutable` on a `DateTimeImmutable` property
- Severity: WARNING

## [repository-conventions]

### Prefer SELECT 1 + setMaxResults over COUNT for existence checks
- For yes/no existence checks prefer `->select('1')`, `->setMaxResults(1)` and `[] !== $queryBuilder->getQuery()->getResult()` over `COUNT()`
- Severity: INFO

### Collection finders return native array/iterable with element-typed @return
- Multi-row finders declare a native `array` or `iterable` return type and document the element type (`Entity[]`)
- Severity: INFO

## [commit-conventions]

### Conventional Commits, single line, no scope
- `type: description` (`fix:`, `test:`, `refactor:`, `chore:`, `build:`, `ci:`, `docs:`), English, one line, no version prefix, no attribution trailer
- Reference: `git log --oneline -20`
- Severity: ERROR
