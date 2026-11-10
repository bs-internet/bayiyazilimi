import { router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    GripVertical,
    ImagePlus,
    Trash2,
} from 'lucide-react';
import { useRef, useState } from 'react';
import type { ChangeEvent, DragEvent } from 'react';

import { SectionCard } from '@/components/admin/page';
import { ConfirmDelete } from '@/components/confirm-delete';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { destroy, reorder, store } from '@/routes/panel/shop/products/images';

type Image = { id: string; url: string; name: string | null };

/**
 * Ürün görselleri: sürükle-bırak ile sıralanan ızgara + yükleme kutucuğu.
 * Ürün formunun içinde durabilir — tüm düğmeler `type="button"`, dosya
 * girdisinin adı yok; ürün kaydına karışmaz.
 */
export function ProductImages({
    productId,
    images,
}: {
    productId: string;
    images: Image[];
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [processing, setProcessing] = useState(false);
    const [dragIndex, setDragIndex] = useState<number | null>(null);
    const [overIndex, setOverIndex] = useState<number | null>(null);
    const [fileOver, setFileOver] = useState(false);

    function send(files: File[]) {
        if (files.length === 0) {
            return;
        }

        setProcessing(true);
        router.post(
            store(productId).url,
            { images: files },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);

                    if (inputRef.current) {
                        inputRef.current.value = '';
                    }
                },
            },
        );
    }

    function upload(event: ChangeEvent<HTMLInputElement>) {
        send(Array.from(event.target.files ?? []));
    }

    function saveOrder(ids: string[]) {
        setProcessing(true);
        router.put(
            reorder(productId).url,
            { ids },
            { preserveScroll: true, onFinish: () => setProcessing(false) },
        );
    }

    function moveTo(from: number, to: number) {
        if (from === to || to < 0 || to >= images.length) {
            return;
        }

        const ids = images.map((i) => i.id);
        const [moved] = ids.splice(from, 1);
        ids.splice(to, 0, moved);
        saveOrder(ids);
    }

    function onTileDrop(event: DragEvent, index: number) {
        event.preventDefault();

        if (dragIndex !== null) {
            moveTo(dragIndex, index);
        } else if (event.dataTransfer.files.length > 0) {
            send(Array.from(event.dataTransfer.files));
        }

        setDragIndex(null);
        setOverIndex(null);
    }

    return (
        <SectionCard
            title={t('Görseller')}
            description={t(
                'İlk görsel kapak görselidir. Sıralamak için sürükleyin. JPG/PNG/WebP, en fazla 8 MB.',
            )}
            action={processing ? <Spinner /> : undefined}
        >
            <input
                ref={inputRef}
                type="file"
                accept="image/*"
                multiple
                className="hidden"
                onChange={upload}
            />
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5">
                {images.map((image, index) => (
                    <div
                        key={image.id}
                        draggable={!processing}
                        onDragStart={(e) => {
                            setDragIndex(index);
                            e.dataTransfer.effectAllowed = 'move';
                        }}
                        onDragOver={(e) => {
                            e.preventDefault();
                            setOverIndex(index);
                        }}
                        onDragLeave={() => setOverIndex(null)}
                        onDragEnd={() => {
                            setDragIndex(null);
                            setOverIndex(null);
                        }}
                        onDrop={(e) => onTileDrop(e, index)}
                        className={cn(
                            'group relative flex flex-col overflow-hidden rounded-lg border bg-card transition',
                            dragIndex === index && 'opacity-40',
                            overIndex === index &&
                                dragIndex !== index &&
                                'ring-2 ring-primary',
                            index === 0 && 'border-primary/40',
                        )}
                    >
                        <div className="relative aspect-square bg-white">
                            <img
                                src={image.url}
                                alt={image.name ?? ''}
                                className="size-full object-contain p-1"
                                loading="lazy"
                                draggable={false}
                            />
                            {index === 0 && (
                                <span className="absolute top-1.5 left-1.5 rounded bg-primary px-1.5 py-0.5 text-[10px] font-medium text-primary-foreground">
                                    {t('Kapak')}
                                </span>
                            )}
                            <GripVertical className="absolute top-1.5 right-1.5 size-4 cursor-grab text-muted-foreground opacity-0 transition group-hover:opacity-100" />
                        </div>
                        <div className="flex items-center justify-between border-t px-1 py-1">
                            <div className="flex">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-xs"
                                    aria-label={t('Öne al')}
                                    disabled={processing || index === 0}
                                    onClick={() => moveTo(index, index - 1)}
                                >
                                    <ChevronLeft />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-xs"
                                    aria-label={t('Arkaya al')}
                                    disabled={
                                        processing ||
                                        index === images.length - 1
                                    }
                                    onClick={() => moveTo(index, index + 1)}
                                >
                                    <ChevronRight />
                                </Button>
                            </div>
                            <ConfirmDelete
                                action={destroy({
                                    product: productId,
                                    media: image.id,
                                })}
                                title={t('Görsel silinsin mi?')}
                                description={t('Görsel kalıcı olarak silinir.')}
                            >
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-xs"
                                    aria-label={t('Görseli sil')}
                                    className="text-muted-foreground hover:text-destructive"
                                >
                                    <Trash2 />
                                </Button>
                            </ConfirmDelete>
                        </div>
                    </div>
                ))}

                <button
                    type="button"
                    disabled={processing}
                    onClick={() => inputRef.current?.click()}
                    onDragOver={(e) => {
                        if (dragIndex === null) {
                            e.preventDefault();
                            setFileOver(true);
                        }
                    }}
                    onDragLeave={() => setFileOver(false)}
                    onDrop={(e) => {
                        e.preventDefault();
                        setFileOver(false);

                        if (dragIndex === null) {
                            send(Array.from(e.dataTransfer.files));
                        }
                    }}
                    className={cn(
                        'flex aspect-square flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed p-3 text-center text-sm text-muted-foreground transition hover:border-primary hover:text-primary disabled:opacity-50',
                        fileOver && 'border-primary bg-primary/5 text-primary',
                    )}
                >
                    <ImagePlus className="size-6" />
                    <span className="font-medium">{t('Görsel yükle')}</span>
                    <span className="text-xs">{t('ya da buraya bırakın')}</span>
                </button>
            </div>
        </SectionCard>
    );
}
