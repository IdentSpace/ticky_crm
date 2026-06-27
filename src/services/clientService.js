import axios from '@nextcloud/axios';
import { generateUrl } from '@nextcloud/router';

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
            generateUrl(`/apps/ticky_crm/api/v1/clients/${client.id}`),
            client
        )
        // Gibt den frisch aktualisierten Kunden vom Server zurück
        return response.data
    } catch (error) {
        throw new Error(
            error.response?.data?.message || 'Der Kunde konnte nicht aktualisiert werden.'
        )
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