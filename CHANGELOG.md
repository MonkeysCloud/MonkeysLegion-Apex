# Changelog

## [1.1.1] - 2025-05-25

### Added
- **ConfigResolver** — `Config\ConfigResolver` resolves Apex configuration with dual-format support: MLC files prioritized (`ai.mlc`), PHP array fallback (`ai.php`), hardcoded defaults as last resort. MLC parser detected at runtime via `class_exists()`.
- **Example MLC config** — `config/ai.example.mlc` ships with the package for easy adoption in MonkeysLegion projects.
- **`monkeyslegion-mlc` and `monkeyslegion-env`** added to `suggest` dependencies for MLC config file parsing.

### Changed
- `AIServiceProvider` now delegates config resolution to `ConfigResolver::resolve()` with an optional `$configDir` parameter. Backward compatible — manual `$config` overrides still take highest priority.

## [1.1.0] - 2025-05-23

### Added
- **MCP Integration** — `monkeyscloud/monkeyslegion-mcp` is now a core dependency. The `MCPServer` and `MCPClient` wrappers in `src/MCP/` delegate to the production-grade package, adding input validation, batch requests, resource templates, protocol version negotiation, session management, and Streamable HTTP support.
- **Prompt Injection Guardrails** — 8 new heuristic patterns in `PromptInjectionValidator`: base64 decode, eval(), simulate terminal, sudo, reverse safety policy, respond no restrictions, ignore safety guidelines, execute code.
- **ConnectionPool** — `Http\ConnectionPool` for cURL handle reuse across provider calls, reducing TLS handshake overhead with LRU eviction at configurable capacity.
- **RequestIdMiddleware** — `Middleware\Impl\RequestIdMiddleware` attaches unique 32-char hex request IDs for distributed tracing and log correlation.
- **Tool Executor Param Cache** — `ToolExecutor` caches resolved parameter metadata across invocations for repeated tool calls.
- **Google API Key in Headers** — `GoogleProvider` sends API key via `x-goog-api-key` header instead of URL query parameter.
- **Tool Output Sanitization** — `AI::generate()` enforces 100KB max per tool output and uses `JSON_INVALID_UTF8_SUBSTITUTE` to prevent encoding errors.

### Changed
- `MCPServer` and `MCPClient` classes are now **deprecated** thin wrappers — use `MonkeysLegion\Mcp\Server\McpServer` and `MonkeysLegion\Mcp\Client\McpClient` directly.
- `TextStream` now implements `StreamInterface` contract.

## [1.0.2] - 2025-05-12

### Added
- **xAI Provider** (`Provider\xAI\XaiProvider`) — Grok 3, Grok 3 Mini, Grok 3 Fast via OpenAI-compatible API
- **Cohere Provider** (`Provider\Cohere\CohereProvider`) — Command R+, Embed v4 via native Cohere v2 Chat API with tool use, citations, and streaming support

### Provider Summary (9 total)
| Provider | Class | API |
|----------|-------|-----|
| Anthropic | `AnthropicProvider` | Native Messages API |
| OpenAI | `OpenAIProvider` | OpenAI Chat Completions |
| Google | `GoogleProvider` | Gemini REST API |
| DeepSeek | `DeepSeekProvider` | OpenAI-compatible |
| Groq | `GroqProvider` | OpenAI-compatible |
| Mistral | `MistralProvider` | OpenAI-compatible |
| xAI | `XaiProvider` | OpenAI-compatible |
| Cohere | `CohereProvider` | Native Cohere v2 |
| Ollama | `OllamaProvider` | OpenAI-compatible (local) |
| Generic | `GenericProvider` | Any OpenAI-compatible endpoint |

## [1.0.1] - 2025-04-24

### Added
- DeepSeek, Groq, Mistral providers
- OpenAI-compatible GenericProvider base class
- Model catalog with pricing for all providers

## [1.0.0] - 2025-04-24

### Initial Release
- Core AI facade with generate, stream, extract, embed
- Anthropic, OpenAI, Google, Ollama providers
- FallbackChain router
- CostTracker with PricingRegistry
- Tool execution pipeline
- Structured output extraction
- PSR-16 cache and PSR-3 logger integration
