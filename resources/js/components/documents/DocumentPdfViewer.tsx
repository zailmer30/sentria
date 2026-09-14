import { useTheme } from '@/hooks/useTheme';
import { useTranslations } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { PDFViewerProps, PluginRegistry, ZoomLevel } from '@embedpdf/react-pdf-viewer';
import { usePage } from '@inertiajs/react';
import { type ComponentType, useCallback, useEffect, useRef, useState } from 'react';

export type HallDocumentView = {
    zoom: number;
    page: number;
    relative_x: number;
    relative_y: number;
};

type DocumentPdfViewerProps = {
    src: string;
    className?: string;
    annotations?: unknown[];
    saveUrl?: string;
    /**
     * Read-only rendering for the chamber display. Markup tools are withdrawn
     * and no annotation layer is loaded: annotations here are one member's
     * private working notes, and the hall board is the last place they belong.
     */
    presentation?: boolean;
    /**
     * `driver` publishes zoom/scroll/page changes (secretariat console).
     * `follower` applies incoming view state (session dashboard).
     */
    syncRole?: 'driver' | 'follower';
    /** Current hall view for followers (and initial restore for drivers). */
    view?: HallDocumentView | null;
    /** Fired by the driver when the clerk moves the paper. */
    onViewChange?: (view: HallDocumentView) => void;
};

type AnnotationApi = {
    importAnnotations: (items: unknown[]) => void;
    exportAnnotations: () => {
        wait: (ok: (items: unknown[]) => void, err?: (error: unknown) => void) => void;
    };
    onAnnotationEvent: (cb: (event: { type: string }) => void) => () => void;
    getAnnotations: () => unknown[];
};

type ZoomApi = {
    requestZoom: (level: ZoomLevel) => void;
    getState: () => { zoomLevel: ZoomLevel; currentZoomLevel: number };
    onStateChange: (cb: (event: { documentId: string; state: { currentZoomLevel: number } }) => void) => () => void;
};

type ScrollApi = {
    getCurrentPage: () => number;
    scrollToPage: (options: { pageNumber: number; behavior?: 'instant' | 'smooth' | 'auto' }) => void;
    onPageChange: (cb: (event: { documentId: string; pageNumber: number }) => void) => () => void;
    onScroll: (cb: (event: { documentId: string; metrics: { currentPage: number } }) => void) => () => void;
};

type ViewportApi = {
    getMetrics: () => {
        scrollTop: number;
        scrollLeft: number;
        scrollWidth: number;
        scrollHeight: number;
        clientWidth: number;
        clientHeight: number;
    };
    scrollTo: (position: { x: number; y: number; behavior?: 'instant' | 'smooth' | 'auto' }) => void;
    onScrollChange: (cb: (event: { documentId: string; scrollMetrics: { scrollTop: number; scrollLeft: number } }) => void) => () => void;
};

type SaveState = 'idle' | 'saving' | 'saved' | 'error';

function getCsrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

function annotationId(item: unknown): string | null {
    if (typeof item !== 'object' || item === null || !('annotation' in item)) {
        return null;
    }

    const annotation = (item as { annotation: unknown }).annotation;

    if (typeof annotation !== 'object' || annotation === null || !('id' in annotation)) {
        return null;
    }

    const id = (annotation as { id: unknown }).id;

    return typeof id === 'string' ? id : null;
}

function trackedId(tracked: unknown): string | null {
    if (typeof tracked !== 'object' || tracked === null || !('object' in tracked)) {
        return null;
    }

    const object = (tracked as { object: unknown }).object;

    if (typeof object !== 'object' || object === null || !('id' in object)) {
        return null;
    }

    const id = (object as { id: unknown }).id;

    return typeof id === 'string' ? id : null;
}

function toStoredPayload(items: unknown): unknown[] {
    return JSON.parse(
        JSON.stringify(items, (_key, value: unknown) => (value instanceof ArrayBuffer ? undefined : value)),
    ) as unknown[];
}

function annotationApiFrom(registry: PluginRegistry): AnnotationApi | null {
    const provided = registry.getPlugin('annotation')?.provides?.() as AnnotationApi | undefined;

    if (
        provided &&
        typeof provided.importAnnotations === 'function' &&
        typeof provided.exportAnnotations === 'function' &&
        typeof provided.onAnnotationEvent === 'function' &&
        typeof provided.getAnnotations === 'function'
    ) {
        return provided;
    }

    return null;
}

function zoomApiFrom(registry: PluginRegistry): ZoomApi | null {
    const provided = registry.getPlugin('zoom')?.provides?.() as ZoomApi | undefined;

    if (
        provided &&
        typeof provided.requestZoom === 'function' &&
        typeof provided.getState === 'function' &&
        typeof provided.onStateChange === 'function'
    ) {
        return provided;
    }

    return null;
}

function scrollApiFrom(registry: PluginRegistry): ScrollApi | null {
    const provided = registry.getPlugin('scroll')?.provides?.() as ScrollApi | undefined;

    if (
        provided &&
        typeof provided.getCurrentPage === 'function' &&
        typeof provided.scrollToPage === 'function' &&
        typeof provided.onPageChange === 'function' &&
        typeof provided.onScroll === 'function'
    ) {
        return provided;
    }

    return null;
}

function viewportApiFrom(registry: PluginRegistry): ViewportApi | null {
    const provided = registry.getPlugin('viewport')?.provides?.() as ViewportApi | undefined;

    if (
        provided &&
        typeof provided.getMetrics === 'function' &&
        typeof provided.scrollTo === 'function' &&
        typeof provided.onScrollChange === 'function'
    ) {
        return provided;
    }

    return null;
}

function relativeScroll(metrics: { scrollTop: number; scrollLeft: number; scrollWidth: number; scrollHeight: number; clientWidth: number; clientHeight: number }): {
    relative_x: number;
    relative_y: number;
} {
    const maxX = Math.max(0, metrics.scrollWidth - metrics.clientWidth);
    const maxY = Math.max(0, metrics.scrollHeight - metrics.clientHeight);

    return {
        relative_x: maxX > 0 ? Math.min(1, Math.max(0, metrics.scrollLeft / maxX)) : 0,
        relative_y: maxY > 0 ? Math.min(1, Math.max(0, metrics.scrollTop / maxY)) : 0,
    };
}

function applyRelativeScroll(
    viewport: ViewportApi,
    relativeX: number,
    relativeY: number,
): void {
    const metrics = viewport.getMetrics();
    const maxX = Math.max(0, metrics.scrollWidth - metrics.clientWidth);
    const maxY = Math.max(0, metrics.scrollHeight - metrics.clientHeight);

    viewport.scrollTo({
        x: relativeX * maxX,
        y: relativeY * maxY,
        behavior: 'instant',
    });
}

function viewsClose(a: HallDocumentView, b: HallDocumentView): boolean {
    return (
        a.page === b.page &&
        Math.abs(a.zoom - b.zoom) < 0.01 &&
        Math.abs(a.relative_x - b.relative_x) < 0.005 &&
        Math.abs(a.relative_y - b.relative_y) < 0.005
    );
}

/** Everything a reader may do to the file, withdrawn for the hall board. */
const PRESENTATION_DISABLED = ['annotation', 'redaction', 'selection', 'history'];

export function DocumentPdfViewer({
    src,
    className,
    annotations = [],
    saveUrl,
    presentation = false,
    syncRole,
    view = null,
    onViewChange,
}: DocumentPdfViewerProps) {
    const { t } = useTranslations();
    const { theme } = useTheme();
    const { auth } = usePage<PageProps>().props;
    const [Viewer, setViewer] = useState<ComponentType<PDFViewerProps> | null>(null);
    const [pdfBuffer, setPdfBuffer] = useState<ArrayBuffer | null>(null);
    const [loadedSrc, setLoadedSrc] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [saveState, setSaveState] = useState<SaveState>('idle');
    const annotationsRef = useRef(annotations);
    const saveUrlRef = useRef(saveUrl);
    const nativeIdsRef = useRef(new Set<string>());
    const ignoreEventsRef = useRef(true);
    const teardownRef = useRef<(() => void) | null>(null);
    const syncTeardownRef = useRef<(() => void) | null>(null);
    const applyViewRef = useRef<((next: HallDocumentView) => void) | null>(null);
    const lastPublishedRef = useRef<HallDocumentView | null>(null);
    const viewRef = useRef(view);
    const onViewChangeRef = useRef(onViewChange);
    const syncRoleRef = useRef(syncRole);
    const applyingRef = useRef(false);

    useEffect(() => {
        annotationsRef.current = annotations;
        saveUrlRef.current = saveUrl;
    }, [annotations, saveUrl]);

    useEffect(() => {
        viewRef.current = view;
        onViewChangeRef.current = onViewChange;
        syncRoleRef.current = syncRole;
    }, [view, onViewChange, syncRole]);

    useEffect(() => {
        return () => {
            teardownRef.current?.();
            syncTeardownRef.current?.();
        };
    }, []);

    useEffect(() => {
        let cancelled = false;

        void import('@embedpdf/react-pdf-viewer').then((mod) => {
            if (!cancelled) {
                setViewer(() => mod.PDFViewer);
            }
        });

        return () => {
            cancelled = true;
        };
    }, []);

    useEffect(() => {
        let cancelled = false;

        void fetch(src, { credentials: 'same-origin', headers: { Accept: 'application/pdf' } })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`preview ${response.status}`);
                }

                return response.arrayBuffer();
            })
            .then((buffer) => {
                if (!cancelled) {
                    setPdfBuffer(buffer);
                    setLoadedSrc(src);
                    setError(null);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setError(t('documents.preview_failed'));
                }
            });

        return () => {
            cancelled = true;
        };
        // `t` is a new function every render and must not retrigger this fetch.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [src]);

    useEffect(() => {
        if (!view || !applyViewRef.current || syncRole !== 'follower') {
            return;
        }

        applyViewRef.current(view);
    }, [view, syncRole]);

    const handleReady = useCallback((registry: PluginRegistry) => {
        teardownRef.current?.();
        syncTeardownRef.current?.();

        const zoom = zoomApiFrom(registry);
        const scroll = scrollApiFrom(registry);
        const viewport = viewportApiFrom(registry);

        const publish = () => {
            if (syncRoleRef.current !== 'driver' || applyingRef.current || !zoom || !scroll || !viewport) {
                return;
            }

            const metrics = viewport.getMetrics();
            const relative = relativeScroll(metrics);
            const next: HallDocumentView = {
                zoom: zoom.getState().currentZoomLevel,
                page: scroll.getCurrentPage(),
                relative_x: relative.relative_x,
                relative_y: relative.relative_y,
            };

            const previous = lastPublishedRef.current;

            if (previous && viewsClose(previous, next)) {
                return;
            }

            lastPublishedRef.current = next;
            onViewChangeRef.current?.(next);
        };

        let publishTimer: number | undefined;

        const schedulePublish = () => {
            window.clearTimeout(publishTimer);
            publishTimer = window.setTimeout(publish, 80);
        };

        const applyView = (next: HallDocumentView) => {
            if (!zoom || !scroll || !viewport) {
                return;
            }

            const previous = lastPublishedRef.current;

            if (previous && viewsClose(previous, next)) {
                return;
            }

            applyingRef.current = true;
            lastPublishedRef.current = next;

            try {
                zoom.requestZoom(next.zoom);
                scroll.scrollToPage({ pageNumber: next.page, behavior: 'instant' });
                window.setTimeout(() => {
                    applyRelativeScroll(viewport, next.relative_x, next.relative_y);
                    window.setTimeout(() => {
                        applyingRef.current = false;
                    }, 50);
                }, 40);
            } catch {
                applyingRef.current = false;
            }
        };

        applyViewRef.current = applyView;

        const unsubs: Array<() => void> = [];

        if (syncRoleRef.current === 'driver' && zoom && scroll && viewport) {
            unsubs.push(zoom.onStateChange(() => schedulePublish()));
            unsubs.push(scroll.onPageChange(() => schedulePublish()));
            unsubs.push(scroll.onScroll(() => schedulePublish()));
            unsubs.push(viewport.onScrollChange(() => schedulePublish()));
        }

        if (viewRef.current) {
            window.setTimeout(() => applyView(viewRef.current!), 120);
        }

        syncTeardownRef.current = () => {
            window.clearTimeout(publishTimer);
            for (const unsubscribe of unsubs) {
                unsubscribe();
            }
            applyViewRef.current = null;
        };

        if (presentation) {
            return;
        }

        const api = annotationApiFrom(registry);

        if (api === null) {
            return;
        }

        ignoreEventsRef.current = true;
        nativeIdsRef.current = new Set();

        let saveTimer: number | undefined;
        let loaded = false;

        const persist = () => {
            const endpoint = saveUrlRef.current;

            if (!endpoint) {
                return;
            }

            setSaveState('saving');

            api.exportAnnotations().wait(
                (items) => {
                    const payload = toStoredPayload(
                        items.filter((item) => {
                            const id = annotationId(item);

                            return id === null || !nativeIdsRef.current.has(id);
                        }),
                    );

                    void fetch(endpoint, {
                        method: 'PUT',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': getCsrfToken(),
                        },
                        body: JSON.stringify({ payload }),
                    })
                        .then((response) => {
                            setSaveState(response.ok ? 'saved' : 'error');
                        })
                        .catch(() => {
                            setSaveState('error');
                        });
                },
                () => {
                    setSaveState('error');
                },
            );
        };

        const scheduleSave = () => {
            window.clearTimeout(saveTimer);
            saveTimer = window.setTimeout(persist, 700);
        };

        const hydrate = () => {
            const stored = annotationsRef.current;

            if (Array.isArray(stored) && stored.length > 0) {
                api.importAnnotations(stored);
            }

            window.setTimeout(() => {
                ignoreEventsRef.current = false;
            }, 0);
        };

        const unsubscribe = api.onAnnotationEvent((event) => {
            if (event.type === 'loaded' && !loaded) {
                loaded = true;

                for (const tracked of api.getAnnotations()) {
                    const id = trackedId(tracked);

                    if (id !== null) {
                        nativeIdsRef.current.add(id);
                    }
                }

                hydrate();

                return;
            }

            if (ignoreEventsRef.current) {
                return;
            }

            if (event.type === 'create' || event.type === 'update' || event.type === 'delete') {
                scheduleSave();
            }
        });

        const fallback = window.setTimeout(() => {
            if (!loaded) {
                loaded = true;
                hydrate();
            }
        }, 4000);

        teardownRef.current = () => {
            window.clearTimeout(saveTimer);
            window.clearTimeout(fallback);
            unsubscribe();
        };
    }, [presentation]);

    if (error) {
        return (
            <div className="flex h-full min-h-48 items-center justify-center px-4 text-center text-sm text-critical">{error}</div>
        );
    }

    if (pdfBuffer === null || loadedSrc !== src || Viewer === null) {
        return (
            <div className="flex h-full min-h-48 items-center justify-center text-sm text-ink-muted">
                {t('documents.preview_loading')}
            </div>
        );
    }

    return (
        <div
            className={cn(
                'relative flex h-full min-h-0 flex-col',
                syncRole === 'follower' && 'pointer-events-none',
                className,
            )}
        >
            <Viewer
                key={src}
                className="min-h-0 flex-1"
                style={{ width: '100%', height: '100%', minHeight: 0 }}
                config={{
                    wasmUrl: new URL('/wasm/pdfium.wasm', window.location.origin).href,
                    worker: true,
                    fontFallback: null,
                    fonts: { ui: null, signature: null },
                    stamp: { manifests: [] },
                    tabBar: 'never',
                    disabledCategories: presentation ? PRESENTATION_DISABLED : ['redaction'],
                    theme: { preference: theme },
                    // Frame the whole page: the hall reads a projected document
                    // as a page, and nobody out there can scroll it back.
                    zoom: presentation ? { defaultZoomLevel: 'fit-page' as ZoomLevel } : undefined,
                    annotations: {
                        autoCommit: false,
                        annotationAuthor: auth.user?.display_name ?? '',
                    },
                    documentManager: {
                        initialDocuments: [
                            {
                                buffer: pdfBuffer,
                                name: 'document.pdf',
                                documentId: 'preview',
                            },
                        ],
                    },
                }}
                onReady={handleReady}
            />
            {presentation ? null : (
                <p className="border-t border-line bg-surface-alt px-3 py-1.5 text-2xs text-ink-subtle">
                    {saveState === 'saving'
                        ? t('documents.annotations_saving')
                        : saveState === 'error'
                          ? t('documents.annotations_save_failed')
                          : saveState === 'saved'
                            ? t('documents.annotations_saved')
                            : t('documents.annotations_hint')}
                </p>
            )}
        </div>
    );
}
