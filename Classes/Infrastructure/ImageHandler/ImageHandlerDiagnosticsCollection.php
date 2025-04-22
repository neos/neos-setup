<?php
declare(strict_types=1);

namespace Neos\Neos\Setup\Infrastructure\ImageHandler;

use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 * @internal only for Neos.Neos.Setup, not to be used externally.
 */
readonly class ImageHandlerDiagnosticsCollection implements \IteratorAggregate
{
    /** @param array<int,ImageHandlerDiagnostics> $items */
    public function __construct(private array $items)
    {
    }

    /** @return \Traversable<int,ImageHandlerDiagnostics> */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }

    public function readyCount(): int
    {
        return count(array_filter($this->items, fn(ImageHandlerDiagnostics $h) => $h->isReady));
    }

    public function unavailableCount(): int
    {
        return count(array_filter($this->items, fn(ImageHandlerDiagnostics $h) => !$h->isReady));
    }

    /**
     * @return string[]
     */
    public function driverNames(): array
    {
        return array_values(array_unique(array_map(
            fn(ImageHandlerDiagnostics $h) => $h->descriptor->driverName,
            $this->items
        )));
    }

    /**
     * A driver is ready if it is found at least once in the READY list.
     * NOTE: a driver can appear both as "ready" and "not ready", e.g. for VIPS which has two install options (extension and FFI); then
     *       we count it as "ready"
     */
    public function isReady(string $driverName): bool
    {
        return count(array_filter($this->items, fn(ImageHandlerDiagnostics $h) => $h->descriptor->driverName === $driverName && $h->isReady)) > 0;
    }

    /**
     * The last available driver in the list is the preferred one
     */
    public function preferredDriverName(): string
    {
        $readyHandlers = array_filter(
            $this->items,
            fn(ImageHandlerDiagnostics $h) => $h->isReady
        );
        return end($readyHandlers)?->descriptor?->driverName ?: '';
    }
}
