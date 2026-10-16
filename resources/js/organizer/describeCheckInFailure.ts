/**
 * Classe l'échec d'un appel de check-in (scan, enregistrement, recherche)
 * pour ne jamais confondre un billet refusé (le serveur a répondu, le QR est
 * invalide) et une coupure réseau (le serveur n'a peut-être jamais reçu la
 * requête, ou sa réponse s'est perdue). Un scan rejoué après coupure revient
 * « déjà enregistré » si le premier avait réussi (RecordCheckIn, §4.4) :
 * c'est ce qui rend ce message honnête plutôt qu'alarmiste.
 */
export type CheckInFailureKind = 'network' | 'rejected' | 'session' | 'server';

export interface CheckInFailure {
    kind: CheckInFailureKind;
    message: string;
}

export function describeCheckInFailure(exception: unknown): CheckInFailure {
    if (!window.axios.isAxiosError(exception)) {
        return { kind: 'server', message: "Une erreur inattendue s'est produite." };
    }

    if (exception.response === undefined) {
        return {
            kind: 'network',
            message: "Le serveur n'a pas répondu, l'entrée n'est peut-être pas enregistrée. Rescannez quand le réseau revient : si elle l'était, vous verrez « déjà enregistré ».",
        };
    }

    const status = exception.response.status;
    const data = exception.response.data as { error?: string; message?: string; errors?: Record<string, string[]> } | undefined;

    if (status === 401 || status === 419) {
        return { kind: 'session', message: 'Votre session a expiré : rechargez la page.' };
    }

    if (status === 403) {
        return { kind: 'session', message: "Vous n'avez pas le droit d'effectuer cette action." };
    }

    if (status === 422) {
        return { kind: 'rejected', message: data?.error ?? Object.values(data?.errors ?? {})[0]?.[0] ?? data?.message ?? 'Refusé.' };
    }

    return { kind: 'server', message: "Entrée non confirmée, réessayez." };
}
