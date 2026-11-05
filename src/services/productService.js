import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'

const url = (uuid = '') => generateUrl(`/apps/ticky_crm/api/v1/products${uuid ? '/' + encodeURIComponent(uuid) : ''}`)

export const getProducts = async (offset = 0, query = '', activeOnly = false) => (await axios.get(url(), { params: { limit: 100, offset, query, activeOnly } })).data
export const createProduct = async (product) => (await axios.post(url(), product)).data
export const updateProduct = async (product) => (await axios.put(url(product.uuid), product)).data
export const deleteProduct = async (uuid) => axios.delete(url(uuid))

export const productError = (error, fallback = 'product_error_save') => {
  const data = error.response?.data
  if (data?.error === 'product_invalid_field') {
    return t('ticky_crm', 'product_invalid_field', { field: t('ticky_crm', `product_${data.field}`) })
  }
  const key = ['product_duplicate_number', 'product_not_found', 'client_product_not_found'].includes(data?.error) ? data.error : fallback
  return t('ticky_crm', key)
}

export const newProduct = () => ({
  product_number: '', name: '', description: '', type: 'product',
  procurement_type: null, category: '', unit: 'piece', status: 'active',
  currency: 'EUR', sales_price_net: '', sales_tax_rate: '',
  purchase_price_net: '', purchase_tax_rate: '', internal_note: '',
})
