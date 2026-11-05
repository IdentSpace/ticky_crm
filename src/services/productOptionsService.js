import { ref } from 'vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export const productOptions = ref(null)
const url = generateUrl('/apps/ticky_crm/api/v1/product-options')
export const loadProductOptions = async () => {
  const data = (await axios.get(url)).data
  productOptions.value = data.options
  return data
}
export const saveProductOptions = async options => {
  const data = (await axios.post(url, { options })).data
  productOptions.value = data.options
  return data.options
}
export const productOptionLabel = (field, value) => productOptions.value?.[field]?.find(option => option.id === value)?.label ?? value ?? ''
