import Image from '@tiptap/extension-image';
import { TableKit } from '@tiptap/extension-table';
import TextAlign from '@tiptap/extension-text-align';
import { EditorContent, useEditor, useEditorState } from '@tiptap/react';
import type { Editor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import {
    AlignCenter,
    AlignLeft,
    AlignRight,
    Bold,
    Code,
    Heading2,
    Heading3,
    ImagePlus,
    Italic,
    Link2,
    List,
    ListOrdered,
    Pilcrow,
    Quote,
    Redo2,
    Table as TableIcon,
    Underline as UnderlineIcon,
    Undo2,
} from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';
import { useState } from 'react';

import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type Props = {
    /** Gizli input adı: Inertia <Form> değeri buradan toplar. */
    name?: string;
    value?: string | null;
    defaultValue?: string | null;
    onChange?: (html: string) => void;
    placeholder?: string;
    minHeight?: string;
    invalid?: boolean;
    id?: string;
};

/**
 * TipTap tabanlı zengin metin editörü. Kontrollü (value/onChange) ya da
 * kontrolsüz (defaultValue + name) kullanılabilir; her durumda `name` verilirse
 * güncel HTML gizli input'a yazılır. "HTML" düğmesi kaynak görünümüne geçer.
 */
export function RichTextEditor({
    name,
    value,
    defaultValue,
    onChange,
    placeholder,
    minHeight = '12rem',
    invalid = false,
    id,
}: Props) {
    const [html, setHtml] = useState<string>(value ?? defaultValue ?? '');
    const [sourceMode, setSourceMode] = useState(false);
    const current = value ?? html;

    const update = (next: string) => {
        setHtml(next);
        onChange?.(next);
    };

    const editor = useEditor({
        immediatelyRender: false,
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3, 4] },
                link: { openOnClick: false, autolink: true },
            }),
            Image,
            TableKit.configure({ table: { resizable: false } }),
            TextAlign.configure({ types: ['heading', 'paragraph'] }),
        ],
        content: current,
        editorProps: {
            attributes: {
                class: 'outline-none px-3 py-2',
                'data-placeholder': placeholder ?? '',
            },
        },
        onUpdate: ({ editor: e }) => {
            const next = e.isEmpty ? '' : e.getHTML();
            update(next);
        },
    });

    const toggleSource = () => {
        if (sourceMode && editor) {
            editor.commands.setContent(current, { emitUpdate: false });
        }

        setSourceMode(!sourceMode);
    };

    return (
        <div
            className={cn(
                'rounded-md border border-input bg-transparent shadow-xs dark:bg-input/30',
                invalid && 'border-destructive',
            )}
        >
            {name ? <input type="hidden" name={name} value={current} /> : null}
            <Toolbar
                editor={editor}
                sourceMode={sourceMode}
                onToggleSource={toggleSource}
            />
            {sourceMode ? (
                <Textarea
                    id={id}
                    value={current}
                    onChange={(e) => update(e.target.value)}
                    className="rounded-none border-0 font-mono text-xs shadow-none focus-visible:ring-0"
                    style={{ minHeight }}
                />
            ) : (
                <EditorContent
                    id={id}
                    editor={editor}
                    style={{ minHeight }}
                    className={cn(
                        'max-w-none text-sm [&_.ProseMirror]:min-h-[inherit]',
                        '[&_h2]:mt-4 [&_h2]:mb-2 [&_h2]:text-xl [&_h2]:font-semibold',
                        '[&_h3]:mt-3 [&_h3]:mb-2 [&_h3]:text-lg [&_h3]:font-semibold',
                        '[&_h4]:mt-3 [&_h4]:mb-1 [&_h4]:font-semibold',
                        '[&_ol]:my-2 [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:my-2 [&_ul]:my-2 [&_ul]:list-disc [&_ul]:pl-6',
                        '[&_blockquote]:border-l-4 [&_blockquote]:pl-3 [&_blockquote]:text-muted-foreground',
                        '[&_a]:text-primary [&_a]:underline [&_img]:max-w-full [&_img]:rounded',
                        '[&_table]:my-2 [&_table]:w-full [&_table]:border-collapse [&_td]:border [&_td]:p-1.5 [&_th]:border [&_th]:bg-muted [&_th]:p-1.5 [&_th]:text-left',
                    )}
                />
            )}
        </div>
    );
}

function Toolbar({
    editor,
    sourceMode,
    onToggleSource,
}: {
    editor: Editor | null;
    sourceMode: boolean;
    onToggleSource: () => void;
}) {
    const state = useEditorState({
        editor,
        selector: ({ editor: e }) =>
            e
                ? {
                      h2: e.isActive('heading', { level: 2 }),
                      h3: e.isActive('heading', { level: 3 }),
                      paragraph: e.isActive('paragraph'),
                      bold: e.isActive('bold'),
                      italic: e.isActive('italic'),
                      underline: e.isActive('underline'),
                      bullet: e.isActive('bulletList'),
                      ordered: e.isActive('orderedList'),
                      quote: e.isActive('blockquote'),
                      link: e.isActive('link'),
                      left: e.isActive({ textAlign: 'left' }),
                      center: e.isActive({ textAlign: 'center' }),
                      right: e.isActive({ textAlign: 'right' }),
                      canUndo: e.can().undo(),
                      canRedo: e.can().redo(),
                  }
                : null,
    });

    const chain = () => editor?.chain().focus();
    const disabled = !editor || sourceMode;

    const setLink = () => {
        if (!editor) {
            return;
        }

        const previous = editor.getAttributes('link').href as
            string | undefined;
        const url = window.prompt(
            t('Bağlantı adresi (boş = kaldır)'),
            previous,
        );

        if (url === null) {
            return;
        }

        if (url === '') {
            chain()?.extendMarkRange('link').unsetLink().run();

            return;
        }

        chain()?.extendMarkRange('link').setLink({ href: url }).run();
    };

    const addImage = () => {
        const url = window.prompt(t('Görsel adresi (URL)'));

        if (url) {
            chain()?.setImage({ src: url }).run();
        }
    };

    return (
        <div className="flex flex-wrap items-center gap-0.5 border-b bg-muted/40 p-1">
            <ToolButton
                icon={Pilcrow}
                label={t('Paragraf')}
                active={state?.paragraph}
                disabled={disabled}
                onClick={() => chain()?.setParagraph().run()}
            />
            <ToolButton
                icon={Heading2}
                label={t('Başlık 2')}
                active={state?.h2}
                disabled={disabled}
                onClick={() => chain()?.toggleHeading({ level: 2 }).run()}
            />
            <ToolButton
                icon={Heading3}
                label={t('Başlık 3')}
                active={state?.h3}
                disabled={disabled}
                onClick={() => chain()?.toggleHeading({ level: 3 }).run()}
            />
            <Divider />
            <ToolButton
                icon={Bold}
                label={t('Kalın')}
                active={state?.bold}
                disabled={disabled}
                onClick={() => chain()?.toggleBold().run()}
            />
            <ToolButton
                icon={Italic}
                label={t('İtalik')}
                active={state?.italic}
                disabled={disabled}
                onClick={() => chain()?.toggleItalic().run()}
            />
            <ToolButton
                icon={UnderlineIcon}
                label={t('Altı çizili')}
                active={state?.underline}
                disabled={disabled}
                onClick={() => chain()?.toggleUnderline().run()}
            />
            <Divider />
            <ToolButton
                icon={List}
                label={t('Madde listesi')}
                active={state?.bullet}
                disabled={disabled}
                onClick={() => chain()?.toggleBulletList().run()}
            />
            <ToolButton
                icon={ListOrdered}
                label={t('Numaralı liste')}
                active={state?.ordered}
                disabled={disabled}
                onClick={() => chain()?.toggleOrderedList().run()}
            />
            <ToolButton
                icon={Quote}
                label={t('Alıntı')}
                active={state?.quote}
                disabled={disabled}
                onClick={() => chain()?.toggleBlockquote().run()}
            />
            <Divider />
            <ToolButton
                icon={AlignLeft}
                label={t('Sola hizala')}
                active={state?.left}
                disabled={disabled}
                onClick={() => chain()?.setTextAlign('left').run()}
            />
            <ToolButton
                icon={AlignCenter}
                label={t('Ortala')}
                active={state?.center}
                disabled={disabled}
                onClick={() => chain()?.setTextAlign('center').run()}
            />
            <ToolButton
                icon={AlignRight}
                label={t('Sağa hizala')}
                active={state?.right}
                disabled={disabled}
                onClick={() => chain()?.setTextAlign('right').run()}
            />
            <Divider />
            <ToolButton
                icon={Link2}
                label={t('Bağlantı')}
                active={state?.link}
                disabled={disabled}
                onClick={setLink}
            />
            <ToolButton
                icon={ImagePlus}
                label={t('Görsel (URL)')}
                disabled={disabled}
                onClick={addImage}
            />
            <ToolButton
                icon={TableIcon}
                label={t('Tablo ekle')}
                disabled={disabled}
                onClick={() =>
                    chain()
                        ?.insertTable({ rows: 3, cols: 3, withHeaderRow: true })
                        .run()
                }
            />
            <Divider />
            <ToolButton
                icon={Undo2}
                label={t('Geri al')}
                disabled={disabled || !state?.canUndo}
                onClick={() => chain()?.undo().run()}
            />
            <ToolButton
                icon={Redo2}
                label={t('İleri al')}
                disabled={disabled || !state?.canRedo}
                onClick={() => chain()?.redo().run()}
            />
            <div className="ml-auto">
                <ToolButton
                    icon={Code}
                    label={sourceMode ? t('Editöre dön') : t('HTML kaynağı')}
                    active={sourceMode}
                    onClick={onToggleSource}
                >
                    {t('HTML')}
                </ToolButton>
            </div>
        </div>
    );
}

function ToolButton({
    icon: Icon,
    label,
    active = false,
    disabled = false,
    onClick,
    children,
}: {
    icon: ComponentType<{ className?: string }>;
    label: string;
    active?: boolean;
    disabled?: boolean;
    onClick: () => void;
    children?: ReactNode;
}) {
    return (
        <button
            type="button"
            title={label}
            aria-label={label}
            aria-pressed={active}
            disabled={disabled}
            onClick={onClick}
            className={cn(
                'inline-flex h-7 cursor-pointer items-center gap-1 rounded px-1.5 text-xs text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground disabled:cursor-not-allowed disabled:opacity-40',
                active && 'bg-accent text-accent-foreground',
            )}
        >
            <Icon className="size-4" />
            {children}
        </button>
    );
}

function Divider() {
    return <span className="mx-0.5 h-5 w-px bg-border" aria-hidden />;
}
