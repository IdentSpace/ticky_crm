import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const url = (clientUuid, positionUuid = '') => generateUrl(`/apps/ticky_crm/api/v1/clients/${encodeURIComponent(clientUuid)}/products${positionUuid ? '/' + encodeURIComponent(positionUuid) : ''}`)

export const getClientProducts = async clientUuid => (await axios.get(url(clientUuid))).data
export const createClientProduct = async (clientUuid, data) => (await axios.post(url(clientUuid), data)).data
export const updateClientProduct = async (clientUuid, data) => (await axios.put(url(clientUuid, data.uuid), data)).data
export const deleteClientProduct = async (clientUuid, positionUuid) => axios.delete(url(clientUuid, positionUuid))
