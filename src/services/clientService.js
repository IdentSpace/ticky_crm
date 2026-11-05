import axios from '@nextcloud/axios';
import { generateUrl } from '@nextcloud/router';
import { t } from '@nextcloud/l10n';

export const clientError = (error) => {
    const data = error.response?.data;
    if (data?.error === 'duplicate_client_number') {
        return t('ticky_crm', 'error_duplicate_client_number');
    }
    if (data?.error === 'client_invalid_field') {
        const key = `field_${data.field}`;
        const label = t('ticky_crm', key);
        return t('ticky_crm', 'client_invalid_field', { field: label === key ? data.field : label });
    }
    return t('ticky_crm', 'error_save');
};

export const getClients = async () => {
    const url = generateUrl('/apps/ticky_crm/api/v1/clients');
    try {
        const response = await axios.get(url);
        return response.data; // Axios liefert das JSON-Array in response.data
    } catch (error) {
        console.error('Fehler beim Laden der Kunden:', error.response?.data || error.message);
        throw new Error(error.response?.data?.message || 'Kundenliste konnte nicht geladen werden.');
    }
};

export const createClient = async (clientData) => {
    const url = generateUrl('/apps/ticky_crm/api/v1/clients');
    const response = await axios.post(url, clientData);
    return response.data;
};

/**
 * Aktualisiert einen bestehenden Kunden auf dem Server
 */
export const updateClient = async (client) => {
    try {
        // Sendet den Request an z.B. /apps/ticky/api/v1/clients/{id}
        const response = await axios.put(
            generateUrl(`/apps/ticky_crm/api/v1/clients/${client.uuid}`),
            client
        )
        // Gibt den frisch aktualisierten Kunden vom Server zurück
        return response.data
    } catch (error) {
        throw new Error(clientError(error))
    }
}

export const deleteClient = async (uuid) => {
    try {
        // HIER: uuid in die URL packen
        await axios.delete(generateUrl(`/apps/ticky_crm/api/v1/clients/${uuid}`))
        return true
    } catch (error) {
        throw new Error(error.response?.data?.message || 'Fehler beim Löschen.')
    }
}
