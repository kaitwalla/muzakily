import { ref, onMounted, onUnmounted } from 'vue';

export function useInfiniteScroll(
    hasMore: () => boolean,
    loadMore: () => Promise<void> | void
) {
    const sentinel = ref<HTMLElement | null>(null);
    let observer: IntersectionObserver | null = null;

    function handleIntersection(entries: IntersectionObserverEntry[]): void {
        if (entries[0].isIntersecting && hasMore()) {
            loadMore();
        }
    }

    onMounted(() => {
        if (!sentinel.value) return;
        observer = new IntersectionObserver(handleIntersection, {
            rootMargin: '300px',
        });
        observer.observe(sentinel.value);
    });

    onUnmounted(() => {
        observer?.disconnect();
    });

    return { sentinel };
}
