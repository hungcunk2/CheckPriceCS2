<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class TrackedInventoryStore
{
    private const FILE = 'tracked_inventories.json';

    /**
     * @return Collection<int, object>
     */
    public function all(): Collection
    {
        return $this->mapRows($this->read());
    }

    /**
     * @return Collection<int, object>
     */
    public function publicInventories(): Collection
    {
        return $this->all()
            ->filter(fn (object $row) => ($row->is_public ?? true) !== false)
            ->sortBy(fn (object $row) => sprintf('%05d-%s', $row->sort_order ?? 0, $row->updated_at ?? ''))
            ->values();
    }

    public function find(int $id): ?object
    {
        return $this->all()->first(fn (object $row) => (int) $row->id === $id);
    }

    public function findPublic(int $id): ?object
    {
        $row = $this->find($id);

        if (! $row || ($row->is_public ?? true) === false) {
            return null;
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsert(array $attributes, ?int $id = null): object
    {
        $rows = $this->read();
        $now = now()->toIso8601String();

        if ($id) {
            foreach ($rows as $index => $row) {
                if ((int) ($row['id'] ?? 0) === $id) {
                    $rows[$index] = array_merge($row, $attributes, ['updated_at' => $now]);

                    $this->write($rows);

                    return (object) $rows[$index];
                }
            }
        }

        foreach ($rows as $index => $row) {
            if (($row['url'] ?? '') === ($attributes['url'] ?? '')) {
                $rows[$index] = array_merge($row, $attributes, ['updated_at' => $now]);
                $this->write($rows);

                return (object) $rows[$index];
            }
        }

        $row = array_merge([
            'id' => $this->nextId($rows),
            'is_public' => true,
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], $attributes);
        $rows[] = $row;
        $this->write($rows);

        return (object) $row;
    }

    public function delete(int $id): void
    {
        $rows = array_values(array_filter(
            $this->read(),
            fn ($row) => (int) ($row['id'] ?? 0) !== $id
        ));
        $this->write($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return Collection<int, object>
     */
    private function mapRows(array $rows): Collection
    {
        return collect($rows)
            ->sortByDesc('updated_at')
            ->map(fn (array $row) => (object) $row)
            ->values();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function read(): array
    {
        if (! Storage::disk('local')->exists(self::FILE)) {
            return [];
        }

        $decoded = json_decode(Storage::disk('local')->get(self::FILE), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function write(array $rows): void
    {
        Storage::disk('local')->put(self::FILE, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function nextId(array $rows): int
    {
        $max = collect($rows)->max('id');

        return ($max ?? 0) + 1;
    }
}
