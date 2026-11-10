import { Head } from '@inertiajs/react';

import { ProductForm } from '@/components/shop/product-form';
import type {
    ProductFormData,
    ProductFormProps,
} from '@/components/shop/product-form';
import { t } from '@/lib/i18n';
import { index } from '@/routes/panel/shop/products';

type Props = ProductFormProps & { product: ProductFormData };

export default function ProductEdit(props: Props) {
    return (
        <>
            <Head title={props.product.name} />
            <ProductForm {...props} />
        </>
    );
}

ProductEdit.layout = {
    breadcrumbs: [{ title: t('Ürünler'), href: index() }],
};
