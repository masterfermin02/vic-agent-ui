export type AgentPerformance = {
    callsToday: number;
    totalTalkSeconds: number;
    avgDurationSeconds: number;
    conversionRate: number;
};

export type DispositionRecord = {
    calledAt: string;
    status: string;
    agentId: string;
    durationSeconds: number;
    notes: string;
};

export type Lead = {
    id: number;
    firstName: string;
    lastName: string;
    phone: string;
    phoneCode: string;
    status: string;
    email: string;
    address: string;
    notes: string;
    calledCount: number;
    previousDispositions: DispositionRecord[];
    customFields: Record<string, string>;
};

export type AgentSession = {
    id: number;
    user_id: number;
    campaign_id: string;
    campaign_name: string | null;
    status: 'waiting' | 'ready' | 'incall' | 'paused' | 'wrapup' | 'logged_out';
    asterisk_channel: string | null;
    current_lead_id: string | null;
    current_phone: string | null;
    current_lead_name: string | null;
    call_started_at: string | null;
};

export type Disposition = {
    status: string;
    status_name: string;
    sale: string;
    dnc: string;
};

export type SipConfig = {
    extension: string;
    sipAuthUser: string;
    sipAltAuthUser: string | null;
    sipPassword: string;
    sipAltPassword: string | null;
    sipServer: string;
    wsUrl: string;
    codecs: string[];
    autoAnswer: boolean;
    mute: boolean;
    dialpad: boolean;
    debug: boolean;
};

export type Campaign = {
    campaign_id: string;
    campaign_name: string;
    dial_method: string;
};
