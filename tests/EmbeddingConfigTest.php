<?php

declare(strict_types=1);

namespace MonkeysLegion\Apex\Provider {
    class CurlMock
    {
        public static bool $enabled = false;
        public static ?array $lastCall = null;
        public static ?array $response = null;
        public static int $httpCode = 200;
    }

    function curl_init(?string $url = null)
    {
        if (!CurlMock::$enabled) {
            return \curl_init($url);
        }
        CurlMock::$lastCall = ['url' => $url];
        return 'mock-curl-handle';
    }

    function curl_setopt_array($ch, array $options): bool
    {
        if (!CurlMock::$enabled) {
            return \curl_setopt_array($ch, $options);
        }
        if (isset($options[CURLOPT_POSTFIELDS])) {
            CurlMock::$lastCall['body'] = json_decode($options[CURLOPT_POSTFIELDS], true);
        }
        return true;
    }

    function curl_setopt($ch, int $option, $value): bool
    {
        if (!CurlMock::$enabled) {
            return \curl_setopt($ch, $option, $value);
        }
        if ($option === CURLOPT_POSTFIELDS) {
            CurlMock::$lastCall['body'] = json_decode($value, true);
        }
        return true;
    }

    function curl_exec($ch)
    {
        if (!CurlMock::$enabled) {
            return \curl_exec($ch);
        }
        return json_encode(CurlMock::$response ?? []);
    }

    function curl_getinfo($ch, int $opt = 0)
    {
        if (!CurlMock::$enabled) {
            return \curl_getinfo($ch, $opt);
        }
        if ($opt === CURLINFO_HTTP_CODE) {
            return CurlMock::$httpCode;
        }
        return null;
    }

    function curl_error($ch): string
    {
        if (!CurlMock::$enabled) {
            return \curl_error($ch);
        }
        return '';
    }

    function curl_close($ch): void
    {
        if (!CurlMock::$enabled) {
            \curl_close($ch);
        }
    }
}

namespace MonkeysLegion\Apex\Tests {

    use MonkeysLegion\Apex\Http\AIServiceProvider;
    use MonkeysLegion\Apex\Provider\Cohere\CohereProvider;
    use MonkeysLegion\Apex\Provider\Google\GoogleProvider;
    use MonkeysLegion\Apex\Provider\Ollama\OllamaProvider;
    use MonkeysLegion\Apex\Provider\OpenAI\OpenAIProvider;
    use MonkeysLegion\Apex\Provider\OpenAICompatible\GenericProvider;
    use MonkeysLegion\Apex\Provider\Mistral\MistralProvider;
    use MonkeysLegion\Apex\Provider\DeepSeek\DeepSeekProvider;
    use MonkeysLegion\Apex\Provider\Groq\GroqProvider;
    use MonkeysLegion\Apex\Provider\xAI\XaiProvider;
    use MonkeysLegion\Apex\Provider\CurlMock;
    use PHPUnit\Framework\TestCase;

    final class EmbeddingConfigTest extends TestCase
    {
        protected function setUp(): void
        {
            CurlMock::$enabled = true;
            CurlMock::$lastCall = null;
            CurlMock::$response = null;
            CurlMock::$httpCode = 200;
        }

        protected function tearDown(): void
        {
            CurlMock::$enabled = false;
        }

        public function test_abstract_provider_getter_setter(): void
        {
            $provider = new class('key', 'model') extends \MonkeysLegion\Apex\Provider\AbstractProvider {
                public function name(): string
                {
                    return 'test';
                }
                public function chat(array $messages, array $options = []): \MonkeysLegion\Apex\DTO\Response
                {
                    return new \MonkeysLegion\Apex\DTO\Response(
                        '',
                        \MonkeysLegion\Apex\Enum\FinishReason::Stop,
                        new \MonkeysLegion\Apex\DTO\Usage(0, 0),
                        null,
                        'model',
                        'test'
                    );
                }
                public function streamChat(array $messages, array $options = []): \Generator
                {
                    yield from [];
                }
                public function embed(array $inputs): array
                {
                    return [];
                }
                public function modelInfo(string $model): \MonkeysLegion\Apex\DTO\ModelInfo
                {
                    return new \MonkeysLegion\Apex\DTO\ModelInfo('model', 'test', \MonkeysLegion\Apex\Enum\ModelTier::Fast, 0, 0, 0, 0);
                }
                public function listModels(): array
                {
                    return [];
                }
                protected function buildHeaders(): array
                {
                    return [];
                }
                protected function mapResponse(array $raw, float $latencyMs = 0.0): \MonkeysLegion\Apex\DTO\Response
                {
                    return new \MonkeysLegion\Apex\DTO\Response(
                        '',
                        \MonkeysLegion\Apex\Enum\FinishReason::Stop,
                        new \MonkeysLegion\Apex\DTO\Usage(0, 0),
                        null,
                        'model',
                        'test'
                    );
                }
                protected function mapMessages(array $messages): array
                {
                    return [];
                }
            };

            $this->assertNull($provider->getEmbeddingModel());
            $provider->setEmbeddingModel('custom-embed-model');
            $this->assertSame('custom-embed-model', $provider->getEmbeddingModel());
        }

        public function test_google_provider_embedding_config(): void
        {
            // 1. Default fallback
            $provider = new GoogleProvider('test-key');
            CurlMock::$response = ['embeddings' => [['values' => [0.1, 0.2]]]];

            $this->assertNull($provider->getEmbeddingModel());
            $provider->embed(['hello']);
            $this->assertSame('https://generativelanguage.googleapis.com/v1beta/models/text-embedding-004:batchEmbedContents', CurlMock::$lastCall['url']);
            $this->assertSame('models/text-embedding-004', CurlMock::$lastCall['body']['requests'][0]['model']);

            // 2. Configured in constructor
            $providerConfigured = new GoogleProvider(
                apiKey: 'test-key',
                embeddingModel: 'my-custom-google-embed'
            );

            $this->assertSame('my-custom-google-embed', $providerConfigured->getEmbeddingModel());
            $providerConfigured->embed(['hello']);
            $this->assertSame('https://generativelanguage.googleapis.com/v1beta/models/my-custom-google-embed:batchEmbedContents', CurlMock::$lastCall['url']);
            $this->assertSame('models/my-custom-google-embed', CurlMock::$lastCall['body']['requests'][0]['model']);

            // 3. Prefix preservation
            $providerPrefixed = new GoogleProvider(
                apiKey: 'test-key',
                embeddingModel: 'models/another-google-embed'
            );

            $this->assertSame('models/another-google-embed', $providerPrefixed->getEmbeddingModel());
            $providerPrefixed->embed(['hello']);
            $this->assertSame('https://generativelanguage.googleapis.com/v1beta/models/another-google-embed:batchEmbedContents', CurlMock::$lastCall['url']);
            $this->assertSame('models/another-google-embed', CurlMock::$lastCall['body']['requests'][0]['model']);
        }

        public function test_openai_provider_embedding_config(): void
        {
            // 1. Default fallback
            $provider = new OpenAIProvider('test-key', 'gpt-4.1');
            CurlMock::$response = ['data' => [['embedding' => [0.1, 0.2]]]];

            $this->assertNull($provider->getEmbeddingModel());
            $provider->embed(['hello']);
            $this->assertSame('text-embedding-3-small', CurlMock::$lastCall['body']['model']);

            // 2. Configured in constructor via parent
            $providerConfigured = new OpenAIProvider(
                apiKey: 'test-key',
                model: 'gpt-4.1',
                embeddingModel: 'my-custom-openai-embed'
            );

            $this->assertSame('my-custom-openai-embed', $providerConfigured->getEmbeddingModel());
            $providerConfigured->embed(['hello']);
            $this->assertSame('my-custom-openai-embed', CurlMock::$lastCall['body']['model']);
        }

        public function test_cohere_provider_embedding_config(): void
        {
            // 1. Default fallback
            $provider = new CohereProvider('test-key');
            CurlMock::$response = ['embeddings' => ['float' => [[0.1, 0.2]]]];

            $this->assertNull($provider->getEmbeddingModel());
            $provider->embed(['hello']);
            $this->assertSame('embed-v4.0', CurlMock::$lastCall['body']['model']);

            // 2. Configured in constructor
            $providerConfigured = new CohereProvider(
                apiKey: 'test-key',
                embeddingModel: 'my-custom-cohere-embed'
            );

            $this->assertSame('my-custom-cohere-embed', $providerConfigured->getEmbeddingModel());
            $providerConfigured->embed(['hello']);
            $this->assertSame('my-custom-cohere-embed', CurlMock::$lastCall['body']['model']);
        }

        public function test_ollama_provider_embedding_config(): void
        {
            // 1. Default fallback
            $provider = new OllamaProvider('llama3');
            CurlMock::$response = ['embeddings' => [[0.1, 0.2]]];

            $this->assertNull($provider->getEmbeddingModel());
            $provider->embed(['hello']);
            $this->assertSame('llama3', CurlMock::$lastCall['body']['model']);

            // 2. Configured in constructor
            $providerConfigured = new OllamaProvider(
                model: 'llama3',
                embeddingModel: 'my-custom-ollama-embed'
            );

            $this->assertSame('my-custom-ollama-embed', $providerConfigured->getEmbeddingModel());
            $providerConfigured->embed(['hello']);
            $this->assertSame('my-custom-ollama-embed', CurlMock::$lastCall['body']['model']);
        }

        public function test_generic_and_other_providers_constructors(): void
        {
            // Test GenericProvider constructor takes embeddingModel
            $generic = new GenericProvider(
                apiKey: 'key',
                model: 'model',
                embeddingModel: 'custom-generic-embed'
            );
            $this->assertSame('custom-generic-embed', $generic->getEmbeddingModel());

            // Test MistralProvider constructor
            $mistral = new MistralProvider(
                apiKey: 'key',
                embeddingModel: 'custom-mistral-embed'
            );
            $this->assertSame('custom-mistral-embed', $mistral->getEmbeddingModel());

            // Test DeepSeekProvider constructor
            $deepseek = new DeepSeekProvider(
                apiKey: 'key',
                embeddingModel: 'custom-deepseek-embed'
            );
            $this->assertSame('custom-deepseek-embed', $deepseek->getEmbeddingModel());

            // Test GroqProvider constructor
            $groq = new GroqProvider(
                apiKey: 'key',
                embeddingModel: 'custom-groq-embed'
            );
            $this->assertSame('custom-groq-embed', $groq->getEmbeddingModel());

            // Test XaiProvider constructor
            $xai = new XaiProvider(
                apiKey: 'key',
                embeddingModel: 'custom-xai-embed'
            );
            $this->assertSame('custom-xai-embed', $xai->getEmbeddingModel());
        }

        public function test_ai_service_provider_injects_embedding_model(): void
        {
            $serviceProvider = new AIServiceProvider([
                'provider' => GoogleProvider::class,
                'api_key'  => 'test-key',
                'model'    => 'gemini-2.5-flash',
                'embedding_model' => 'my-injected-google-embed',
            ]);

            $services = $serviceProvider->register();
            $aiFactory = $services[\MonkeysLegion\Apex\AI::class];

            // Mock a Container or resolve direct
            $ai = $aiFactory(new class {
                public function get(string $id)
                {
                    return null;
                }
            });

            $provider = $ai->provider();
            $this->assertInstanceOf(GoogleProvider::class, $provider);
            $this->assertSame('my-injected-google-embed', $provider->getEmbeddingModel());
        }
    }
}
