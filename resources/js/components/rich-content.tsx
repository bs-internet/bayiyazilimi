/**
 * Panelden girilen zengin metni (sayfalar, ürün açıklaması) gösterir.
 * Biçim `resources/css/rich-content.css`; renkler `--rich-*` değişkenleriyle
 * kapsayıcıdan değiştirilebilir (vitrin kendi renklerini verir).
 */
export function RichContent({
    html,
    className = '',
}: {
    html: string;
    className?: string;
}) {
    return (
        <div
            className={`rich-content ${className}`}
            dangerouslySetInnerHTML={{ __html: html }}
        />
    );
}
