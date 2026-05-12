# Changelog

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
