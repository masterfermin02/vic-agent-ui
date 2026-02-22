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

export type Campaign = {
    campaign_id: string;
    campaign_name: string;
    dial_method: string;
};
