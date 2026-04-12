<?php

declare(strict_types=1);

namespace MonkeysLegion\Apex\Memory;

use MonkeysLegion\Apex\Contract\MemoryInterface;
use MonkeysLegion\Apex\DTO\Message;
use MonkeysLegion\Apex\Enum\Role;
use Psr\SimpleCache\CacheInterface;

/**
 * Persistent memory — persists conversation to cache backend.
 */
final class PersistentMemory implements MemoryInterface
{
    /** @var list<Message> */
    private array $messages = [];
    private bool $loaded = false;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly string         $key,
        private readonly int            $ttl = 86400,
    ) {}

    public function add(Message $message): void
    {
        $this->load();
        $this->messages[] = $message;
        $this->save();
    }

    /** @return list<Message> */
    public function messages(): array
    {
        $this->load();
        return $this->messages;
    }

    public function clear(): void
    {
        $this->messages = [];
        $this->cache->delete($this->key);
        $this->loaded = true;
    }

    private function load(): void
    {
        if ($this->loaded) return;
        $this->loaded = true;

        $data = $this->cache->get($this->key);
        if (!is_array($data)) return;

        foreach ($data as $item) {
            $this->messages[] = new Message(
                role: Role::from($item['role']),
                content: $item['content'],
            );
        }
    }

    private function save(): void
    {
        $data = array_map(fn(Message $m) => [
            'role'    => $m->role->value,
            'content' => $m->content,
        ], $this->messages);

        $this->cache->set($this->key, $data, $this->ttl);
    }
}
