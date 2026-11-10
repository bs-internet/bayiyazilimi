import { Head } from '@inertiajs/react';

import { ProductForm } from '@/components/shop/product-form';
import type { ProductFormProps } from '@/components/shop/product-form';
import { t } from '@/lib/i18n';
import { create, index } from '@/routes/panel/shop/products';

export default function ProductCreate(props: ProductFormProps) {
    return (
        <>
            <Head title={t('Yeni ürün')} />
            <ProductForm {...props} product={null} />
        </>
    );
}

ProductCreate.layout = {
    breadcrumbs: [
        { title: t('Ürünler'), href: index() },
        { title: t('Yeni ürün'), href: create() },
    ],
};
