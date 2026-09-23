export type Team = {
    id: number;
    abbreviation: string;
    name: string;
    display_name: string;
    color: string | null;
    logo: string;
};

export type UserAvatar = {
    id: number;
    name: string;
    photo: string | null;
};

export type GameStatus = 'scheduled' | 'in_progress' | 'final';

export type Game = {
    id: number;
    kickoff_at: string;
    status: GameStatus;
    home: Team;
    away: Team;
    home_score: number | null;
    away_score: number | null;
    winning_team_id: number | null;
    is_tiebreaker: boolean;
    is_tbd_flex: boolean;
};

export type WeekPhase = 'draft' | 'open' | 'locked' | 'closed';

export type WeekSummary = {
    id: number;
    number: number;
    season_year: number;
    phase: WeekPhase;
    is_locked: boolean;
    locks_at: string | null;
    closed_at: string | null;
};

export type StandingRow = {
    entry_id: number;
    user: UserAvatar;
    submitted: boolean;
    correct: number;
    placement: number | null;
    is_leader: boolean;
    points: number;
    tiebreaker_guess: number | null;
    tiebreaker_diff: number | null;
    picks: Record<number, number>;
};

export type WeekBoard = {
    week: WeekSummary;
    games: Game[];
    tiebreaker_total: number | null;
    all_games_final: boolean;
    participants: {
        user: UserAvatar;
        submitted: boolean;
        submitted_at: string | null;
        tiebreaker_guess: number | null;
        picks: Record<number, number>;
    }[];
    standings: StandingRow[] | null;
};

export type LeaderboardRow = {
    rank: number;
    user: UserAvatar;
    total_points: number;
    weeks_won: number;
    total_correct: number;
    weeks_played: number;
    dnp_count: number;
    cumulative: number[];
};
