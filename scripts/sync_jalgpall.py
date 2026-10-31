#!/usr/bin/env python3
"""
Paide Linnameeskond — Official EJL OpenData API Sync Script
Base URL: https://opendata.jalgpall.ee/api/v1
Documentation: https://opendata.jalgpall.ee/api/documentation

Replaces legacy HTML scraping with official EJL OpenData API v1 requests for:
  - Men's Premium Liiga standings & season history (/api/v1/competitions/52)
  - Women's Naiste Meistriliiga standings & season history (/api/v1/competitions/63)
  - Men's upcoming & recent matches + goal scorers (/api/v1/teams/5/games & /api/v1/games/{id})
  - Women's upcoming & recent matches + goal scorers (/api/v1/teams/3521/games & /api/v1/games/{id})
  - Men's & Women's top scorers, assists, and team statistics (/api/v1/teams/{id}/statistics & /api/v1/players/{id}/statistics)

Security: Reads EJL_API_TOKEN from environment variables or gitignored `.env` / `config/ejl-secret.php`.
"""

from __future__ import annotations

import json
import os
import re
import sys
import urllib.parse
import urllib.request
from datetime import datetime
from pathlib import Path
from zoneinfo import ZoneInfo

ROOT_DIR = Path(__file__).resolve().parent.parent
DATA_FILE = ROOT_DIR / "data" / "jalgpall-data.json"
TALLINN_TZ = ZoneInfo("Europe/Tallinn")

DAYS_ET = {
    1: "Esmaspäev",
    2: "Teisipäev",
    3: "Kolmapäev",
    4: "Neljapäev",
    5: "Reede",
    6: "Laupäev",
    7: "Pühapäev",
}

MONTHS_ET = {
    1: "jaanuar",
    2: "veebruar",
    3: "märts",
    4: "aprill",
    5: "mai",
    6: "juuni",
    7: "juuli",
    8: "august",
    9: "september",
    10: "oktoober",
    11: "november",
    12: "detsember",
}

LOGO_MAP = {
    "levadia": "assets/img/jalgpall_logos/levadia.png",
    "paide": "assets/img/jalgpall_logos/paide.png",
    "flora": "assets/img/jalgpall_logos/flora.png",
    "kalju": "assets/img/jalgpall_logos/kalju.png",
    "vaprus": "assets/img/jalgpall_logos/vaprus.png",
    "trans": "assets/img/jalgpall_logos/trans.png",
    "harju": "assets/img/jalgpall_logos/harju.png",
    "laagri": "assets/img/jalgpall_logos/harju.png",
    "tammeka": "assets/img/jalgpall_logos/tammeka.png",
    "kuressaare": "assets/img/jalgpall_logos/kuressaare.png",
    "united": "assets/img/jalgpall_logos/united.png",
    "saku": "assets/img/jalgpall_logos/saku.png",
    "viimsi": "assets/img/jalgpall_logos/viimsi.png",
    "elva": "assets/img/jalgpall_logos/elva.png",
    "tabasalu": "assets/img/jalgpall_logos/tabasalu.png",
}


def load_api_config():
    """Loads EJL_API_TOKEN and EJL_API_BASE_URL from env, .env, or config/ejl-secret.php."""
    base_url = os.environ.get("EJL_API_BASE_URL", "https://opendata.jalgpall.ee").strip().rstrip("/")
    token = os.environ.get("EJL_API_TOKEN", "").strip()

    if not token:
        env_file = ROOT_DIR / ".env"
        if env_file.is_file():
            for line in env_file.read_text(encoding="utf-8").splitlines():
                line = line.strip()
                if not line or line.startswith("#") or "=" not in line:
                    continue
                k, v = line.split("=", 1)
                k = k.strip()
                v = v.strip().strip("\"'")
                if k == "EJL_API_TOKEN" and v:
                    token = v
                elif k == "EJL_API_BASE_URL" and v:
                    base_url = v.rstrip("/")

    if not token:
        secret_php = ROOT_DIR / "config" / "ejl-secret.php"
        if secret_php.is_file():
            content = secret_php.read_text(encoding="utf-8")
            m = re.search(r"['\"]token['\"]\s*=>\s*['\"]([^'\"]+)['\"]", content)
            if m and m.group(1) != "YOUR_EJL_BEARER_TOKEN_HERE":
                token = m.group(1).strip()

    if not token:
        raise RuntimeError(
            "Missing EJL_API_TOKEN. Set EJL_API_TOKEN in environment, .env, or config/ejl-secret.php."
        )

    return base_url, token


BASE_URL, API_TOKEN = load_api_config()


def api_get(path: str, params: dict | None = None, timeout: int = 15):
    """Performs an authenticated GET request against the EJL OpenData API."""
    url = f"{BASE_URL}/{path.lstrip('/')}"
    if params:
        qs = urllib.parse.urlencode({k: v for k, v in params.items() if v is not None})
        if qs:
            url = f"{url}?{qs}"

    req = urllib.request.Request(
        url,
        headers={
            "Authorization": f"Bearer {API_TOKEN}",
            "Accept": "application/json",
            "User-Agent": "PaideLinnameeskond-OfficialWebsite/2.0",
        },
    )
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        return json.loads(resp.read().decode("utf-8"))


def clean_team_name(name: str) -> str:
    clean = re.sub(r"\s+", " ", (name or "")).strip()
    clean = re.sub(r"\s*\(N\)\s*$", "", clean, flags=re.IGNORECASE).strip()
    return clean


def resolve_local_logo(team_name: str) -> str:
    lower = (team_name or "").lower()
    for needle, path in LOGO_MAP.items():
        if needle in lower:
            return path
    return ""


def format_short_player_name(full_name: str) -> str:
    clean = re.sub(r"\s+", " ", (full_name or "")).strip()
    if not clean:
        return ""
    parts = clean.split(" ")
    if len(parts) == 1:
        return parts[0]
    last_name = parts[-1]
    initials = "".join(f"{p[0]}." for p in parts[:-1] if p)
    return f"{initials} {last_name}"


def parse_ejl_datetime(iso_str: str) -> dict:
    if not iso_str:
        return {
            "timestamp": 0,
            "dateStr": "",
            "timeStr": "",
            "dateDisplay": "",
            "dateTimeStr": "",
        }
    dt = datetime.fromisoformat(iso_str).astimezone(TALLINN_TZ)
    date_str = dt.strftime("%d.%m.%Y")
    time_str = dt.strftime("%H:%M")
    dow = DAYS_ET.get(dt.isoweekday(), "")
    month_et = MONTHS_ET.get(dt.month, "")
    date_display = f"{dow}, {dt.day}. {month_et}"
    return {
        "timestamp": int(dt.timestamp()),
        "dateStr": date_str,
        "timeStr": time_str,
        "dateDisplay": date_display,
        "dateTimeStr": f"{date_str} {time_str}",
    }


def extract_game_goals(game_detail: dict) -> list:
    if not isinstance(game_detail, dict):
        return []

    home_obj = (game_detail.get("teams") or {}).get("home") or {}
    away_obj = (game_detail.get("teams") or {}).get("away") or {}
    home_id = home_obj.get("id")
    home_name = clean_team_name(home_obj.get("name", ""))
    away_name = clean_team_name(away_obj.get("name", ""))

    player_map = {}
    for t_obj in (home_obj, away_obj):
        for p in t_obj.get("players") or []:
            pid = p.get("id")
            if pid:
                player_map[pid] = f"{p.get('forename', '').strip()} {p.get('surname', '').strip()}".strip()

    goals = []
    running_home = 0
    running_away = 0

    for ev in game_detail.get("events") or []:
        t_obj = ev.get("type") or {}
        code = (t_obj.get("code") or "").upper()
        t_id = t_obj.get("id")

        is_goal = code == "GOAL" or t_id == 1
        is_penalty = code == "PENALTY" or t_id == 3
        is_own_goal = code == "OWN_GOAL" or t_id == 2
        if not (is_goal or is_penalty or is_own_goal):
            continue

        ev_team_id = ev.get("teamId")
        if is_own_goal:
            side = "away" if ev_team_id == home_id else "home"
        else:
            side = "home" if ev_team_id == home_id else "away"

        if side == "home":
            running_home += 1
        else:
            running_away += 1

        minute = ev.get("minute") or 0
        extra = ev.get("extraMinute")
        minute_str = f"{minute}+{extra}'" if extra else (f"{minute}'" if minute else "")

        pid = ev.get("playerId")
        player_name = player_map.get(pid, "")
        if not player_name and pid:
            try:
                p_info = api_get(f"/api/v1/players/{pid}")
                player_name = f"{p_info.get('forename', '').strip()} {p_info.get('surname', '').strip()}".strip()
                if player_name:
                    player_map[pid] = player_name
            except Exception:
                pass

        goal_type = "pen" if is_penalty else ("ov" if is_own_goal else "")
        scoring_team = home_name if side == "home" else away_name

        goals.append({
            "side": side,
            "minute": minute_str,
            "player": player_name,
            "shortPlayer": format_short_player_name(player_name),
            "team": scoring_team,
            "runningScore": f"{running_home} : {running_away}",
            "goalType": goal_type,
            "isPaide": "paide" in scoring_team.lower(),
        })

    return goals


def fetch_competition_season_history(competition_id: int, season: int) -> dict:
    team_matches = {}
    page = 1
    while page <= 5:
        resp = api_get(
            f"/api/v1/competitions/{competition_id}/games",
            {"season": season, "upcoming": 0, "pageSize": 100, "pageNumber": page},
        )
        games = (resp or {}).get("games") or []
        if not games:
            break

        for g in games:
            ft = (g.get("scores") or {}).get("fullTime")
            if not isinstance(ft, dict) or ft.get("home") is None or ft.get("away") is None:
                continue
            hg = int(ft["home"])
            ag = int(ft["away"])
            home_team = clean_team_name(((g.get("teams") or {}).get("home") or {}).get("name", ""))
            away_team = clean_team_name(((g.get("teams") or {}).get("away") or {}).get("name", ""))
            if not home_team or not away_team:
                continue

            dt = parse_ejl_datetime(g.get("dateTime", ""))
            match_score = f"{hg}:{ag}"

            # Home entry
            h_type, h_res = ("win", "V") if hg > ag else (("loss", "K") if hg < ag else ("draw", "X"))
            team_matches.setdefault(home_team, []).append({
                "_ts": dt["timestamp"],
                "type": h_type,
                "result": h_res,
                "score": f"{match_score} vs {away_team}",
                "matchScore": match_score,
                "homeTeam": home_team,
                "awayTeam": away_team,
                "opponent": away_team,
                "date": dt["dateTimeStr"],
                "isHome": True,
            })

            # Away entry
            a_type, a_res = ("win", "V") if ag > hg else (("loss", "K") if ag < hg else ("draw", "X"))
            team_matches.setdefault(away_team, []).append({
                "_ts": dt["timestamp"],
                "type": a_type,
                "result": a_res,
                "score": f"{match_score} vs {home_team}",
                "matchScore": match_score,
                "homeTeam": home_team,
                "awayTeam": away_team,
                "opponent": home_team,
                "date": dt["dateTimeStr"],
                "isHome": False,
            })

        total_pages = int((resp or {}).get("totalPages") or 1)
        if page >= total_pages:
            break
        page += 1

    for t_name, m_list in team_matches.items():
        m_list.sort(key=lambda x: x["_ts"])
        for idx, item in enumerate(m_list, start=1):
            item["round"] = idx
            item.pop("_ts", None)

    return team_matches


def fetch_standings(competition_id: int, season: int, existing_rows: list) -> list:
    comp_resp = api_get(f"/api/v1/competitions/{competition_id}", {"season": season})
    table = (comp_resp or {}).get("table") or []
    if not table:
        return existing_rows

    history_map = fetch_competition_season_history(competition_id, season)
    existing_by_team = {
        clean_team_name(r.get("team", "")).lower(): r for r in (existing_rows or []) if r.get("team")
    }

    standings = []
    for row in table:
        team_obj = row.get("team") or {}
        stats = row.get("stats") or {}
        team_name = clean_team_name(team_obj.get("name", ""))
        if not team_name:
            continue

        rank = int(row.get("rank") or (len(standings) + 1))
        played = int(stats.get("gamesPlayed") or 0)
        won = int(stats.get("wins") or 0)
        drawn = int(stats.get("draws") or 0)
        lost = int(stats.get("losses") or 0)
        gf = int(stats.get("goalsScored") or 0)
        ga = int(stats.get("goalsConceded") or 0)
        diff = int(stats.get("goalsDiff") if stats.get("goalsDiff") is not None else (gf - ga))
        points = int(stats.get("points") if stats.get("points") is not None else (won * 3 + drawn))
        gd_str = f"+{diff}" if diff > 0 else str(diff)

        prev_row = existing_by_team.get(team_name.lower(), {})
        season_history = history_map.get(team_name) or prev_row.get("seasonHistory") or []
        form = [
            {
                "result": h["result"],
                "type": h["type"],
                "score": h["score"],
                "opponent": h["opponent"],
            }
            for h in season_history[-5:]
        ]

        local_logo = resolve_local_logo(team_name) or prev_row.get("localLogo", "")

        standings.append({
            "position": rank,
            "team": team_name,
            "teamId": team_obj.get("id"),
            "logo": team_obj.get("logo") or prev_row.get("logo", ""),
            "localLogo": local_logo,
            "played": played,
            "won": won,
            "drawn": drawn,
            "lost": lost,
            "goalsFor": gf,
            "goalsAgainst": ga,
            "goalDifference": gd_str,
            "points": points,
            "isPaide": "paide" in team_name.lower(),
            "form": form,
            "seasonHistory": season_history,
        })

    return standings


def fetch_team_matches(team_id: int, season: int, is_women: bool = False) -> dict:
    default_code = "N.ML" if is_women else "PL"
    default_tournament = "Naiste Meistriliiga" if is_women else "A. Le Coq Premium Liiga"

    # 1. Upcoming games (upcoming=1)
    up_resp = api_get(f"/api/v1/teams/{team_id}/games", {"season": season, "upcoming": 1, "pageSize": 20})
    upcoming_list = []
    for g in (up_resp or {}).get("games") or []:
        dt = parse_ejl_datetime(g.get("dateTime", ""))
        comp = g.get("competition") or {}
        comp_short = (comp.get("name") or default_code).strip()
        if comp_short == "ML":
            comp_short = "PL"
        comp_long = (comp.get("longName") or default_tournament).strip()
        tour = str(g.get("tour") or "").strip()
        round_display = f"{comp_long} • {tour}. Voor" if tour else comp_long

        home_team = clean_team_name(((g.get("teams") or {}).get("home") or {}).get("name", ""))
        away_team = clean_team_name(((g.get("teams") or {}).get("away") or {}).get("name", ""))
        venue = ((g.get("field") or {}).get("name") or "").strip()
        broadcaster = (((g.get("coverage") or {}).get("broadcaster") or {}).get("name") or "").strip()
        broadcast_str = f"OTSE: {broadcaster.upper()}" if broadcaster else "OTSE: SOCCERNET TV / JALGPALL.EE"
        ticket_url = (g.get("links") or {}).get("tickets")
        gid = g.get("id")

        upcoming_list.append({
            "_ts": dt["timestamp"],
            "id": gid,
            "type": "upcoming",
            "homeTeam": home_team,
            "awayTeam": away_team,
            "date": f"{comp_short} {dt['dateTimeStr']}".strip(),
            "dateStr": dt["dateStr"],
            "timeStr": dt["timeStr"],
            "dateDisplay": dt["dateDisplay"],
            "venue": venue,
            "round": round_display,
            "broadcast": broadcast_str,
            "ticketUrl": ticket_url,
            "matchInfoUrl": f"https://jalgpall.ee/voistlused/protocol/{gid}" if gid else "",
        })

    upcoming_list.sort(key=lambda x: x["_ts"])
    for item in upcoming_list:
        item.pop("_ts", None)

    next_match = upcoming_list[0] if upcoming_list else None

    # 2. Recent played games (upcoming=0)
    past_resp = api_get(f"/api/v1/teams/{team_id}/games", {"season": season, "upcoming": 0, "pageSize": 15})
    recent_list = []
    for g in (past_resp or {}).get("games") or []:
        ft = (g.get("scores") or {}).get("fullTime")
        if not isinstance(ft, dict) or ft.get("home") is None or ft.get("away") is None:
            continue

        gid = g.get("id")
        dt = parse_ejl_datetime(g.get("dateTime", ""))
        comp = g.get("competition") or {}
        comp_short = (comp.get("name") or default_code).strip()
        if comp_short == "ML":
            comp_short = "PL"
        comp_long = (comp.get("longName") or default_tournament).strip()
        tour = str(g.get("tour") or "").strip()
        round_str = f"{tour}. voor" if tour else comp_long

        home_team = clean_team_name(((g.get("teams") or {}).get("home") or {}).get("name", ""))
        away_team = clean_team_name(((g.get("teams") or {}).get("away") or {}).get("name", ""))
        home_score = int(ft["home"])
        away_score = int(ft["away"])
        venue = ((g.get("field") or {}).get("name") or "").strip()
        attendance = g.get("attendance")

        scorers = []
        if gid and len(recent_list) < 5:
            try:
                detail = api_get(f"/api/v1/games/{gid}")
                scorers = extract_game_goals(detail)
            except Exception as exc:
                print(f"Warning: could not fetch game detail {gid}: {exc}")

        recent_list.append({
            "_ts": dt["timestamp"],
            "id": gid,
            "type": "finished",
            "meta": f"{comp_short} {dt['dateStr']} Kell {dt['timeStr']}".strip(),
            "tournament": comp_long,
            "round": round_str,
            "dateDisplay": dt["dateDisplay"],
            "timeDisplay": dt["timeStr"],
            "homeTeam": home_team,
            "score": f"{home_score} - {away_score}",
            "homeScore": home_score,
            "awayScore": away_score,
            "awayTeam": away_team,
            "venue": venue,
            "attendance": attendance,
            "protocolUrl": f"https://jalgpall.ee/voistlused/protocol/{gid}" if gid else "",
            "scorers": scorers,
        })

    recent_list.sort(key=lambda x: x["_ts"], reverse=True)
    for item in recent_list:
        item.pop("_ts", None)

    return {
        "next": next_match,
        "upcoming": upcoming_list,
        "recent": recent_list,
    }


def fetch_team_stats_and_scorers(team_id: int, season: int, is_women: bool = False, existing_scorers: list | None = None) -> tuple[list, dict]:
    stats_resp = api_get(
        f"/api/v1/teams/{team_id}/statistics",
        {"season": season, "includeTopPlayers": 1, "limit": 30},
    )
    if not isinstance(stats_resp, dict):
        return existing_scorers or [], {}

    existing_by_name = {
        (s.get("name") or "").strip().lower(): s for s in (existing_scorers or []) if s.get("name")
    }

    assists_map = {}
    for a_item in stats_resp.get("topPlayersByAssists") or []:
        p_obj = a_item.get("player") or {}
        if p_obj.get("id"):
            assists_map[int(p_obj["id"])] = int(a_item.get("value") or 0)

    league_code = "N.ML" if is_women else "ML"
    cup_code = "NKV" if is_women else "KV"
    top_scorers = []

    for idx, g_item in enumerate((stats_resp.get("topPlayersByGoals") or [])[:10], start=1):
        p_obj = g_item.get("player") or {}
        pid = int(p_obj.get("id") or 0)
        full_name = f"{p_obj.get('forename', '').strip()} {p_obj.get('surname', '').strip()}".strip()
        if not full_name:
            continue

        team_goals_val = int(g_item.get("value") or 0)
        league_goals = team_goals_val
        cup_goals = 0
        api_assists = assists_map.get(pid, 0)
        games_played = 0
        minutes_played = 0

        if pid > 0:
            try:
                p_stat = api_get(f"/api/v1/players/{pid}/statistics", {"season": season})
                lg_goals = 0
                cp_goals = 0
                tot_assists = 0
                for lg in (p_stat or {}).get("leagues") or []:
                    l_name = (lg.get("leagueName") or "").strip().upper()
                    if l_name == league_code:
                        lg_goals += int(lg.get("goals") or 0)
                        tot_assists += int(lg.get("assists") or 0)
                        games_played += int(lg.get("gamesPlayed") or 0)
                        minutes_played += int(lg.get("minutesPlayed") or 0)
                    elif l_name == cup_code:
                        cp_goals += int(lg.get("goals") or 0)
                        tot_assists += int(lg.get("assists") or 0)
                        games_played += int(lg.get("gamesPlayed") or 0)
                        minutes_played += int(lg.get("minutesPlayed") or 0)
                if (lg_goals + cp_goals) > 0:
                    league_goals = lg_goals
                    cup_goals = cp_goals
                    team_goals_val = lg_goals + cp_goals
                if tot_assists > api_assists:
                    api_assists = tot_assists
            except Exception as exc:
                print(f"Warning: could not fetch player stats {pid}: {exc}")

        prev_scorer = existing_by_name.get(full_name.lower(), {})
        final_assists = api_assists
        if final_assists == 0 and prev_scorer.get("assists"):
            final_assists = int(prev_scorer["assists"])

        top_scorers.append({
            "rank": str(idx),
            "id": pid,
            "name": full_name,
            "leagueGoals": str(league_goals),
            "cupGoals": str(cup_goals),
            "totalGoals": str(team_goals_val),
            "assists": str(final_assists),
            "apiAssists": str(api_assists),
            "gamesPlayed": games_played,
            "minutesPlayed": minutes_played,
        })

    top_assists = []
    for idx, a_item in enumerate((stats_resp.get("topPlayersByAssists") or [])[:10], start=1):
        p_obj = a_item.get("player") or {}
        top_assists.append({
            "rank": int(a_item.get("rank") or idx),
            "id": int(p_obj.get("id") or 0),
            "name": f"{p_obj.get('forename', '').strip()} {p_obj.get('surname', '').strip()}".strip(),
            "assists": int(a_item.get("value") or 0),
        })

    team_stats = {
        "gamesPlayed": (stats_resp.get("gamesPlayed") or {}).get("total", 0),
        "goals": (stats_resp.get("goals") or {}).get("total", 0),
        "assists": (stats_resp.get("assists") or {}).get("total", 0),
        "yellowCards": (stats_resp.get("yellowCards") or {}).get("total", 0),
        "redCards": (stats_resp.get("redCards") or {}).get("total", 0),
        "viewers": (stats_resp.get("viewers") or {}).get("total", 0),
        "topAssists": top_assists,
    }

    return (top_scorers if top_scorers else (existing_scorers or [])), team_stats


def main():
    print("Syncing Paide Linnameeskond data from Official EJL OpenData API v1...")
    if not DATA_FILE.is_file():
        print(f"Error: {DATA_FILE} not found.", file=sys.stderr)
        sys.exit(1)

    data = json.loads(DATA_FILE.read_text(encoding="utf-8"))
    season = 2026

    # 1. Standings (Competition 52 = Premium Liiga, Competition 63 = Naiste Meistriliiga)
    print(" -> Fetching Premium Liiga standings & season history (/api/v1/competitions/52)...")
    men_standings = fetch_standings(52, season, (data.get("standings") or {}).get("premiumLiiga", []))
    print(f"    Loaded {len(men_standings)} teams in Premium Liiga.")

    print(" -> Fetching Naiste Meistriliiga standings & season history (/api/v1/competitions/63)...")
    women_standings = fetch_standings(63, season, (data.get("standings") or {}).get("naisteMeistriliiga", []))
    print(f"    Loaded {len(women_standings)} teams in Naiste Meistriliiga.")

    data.setdefault("standings", {})
    data["standings"]["premiumLiiga"] = men_standings
    data["standings"]["naisteMeistriliiga"] = women_standings

    # 2. Matches (Team 5 = Paide Linnameeskond, Team 3521 = Paide Linnanaiskond)
    print(" -> Fetching Men's upcoming & recent matches (/api/v1/teams/5/games)...")
    data["matches"] = fetch_team_matches(5, season, is_women=False)
    print(
        f"    Loaded {len(data['matches']['upcoming'])} upcoming and {len(data['matches']['recent'])} recent Men's matches."
    )

    print(" -> Fetching Women's upcoming & recent matches (/api/v1/teams/3521/games)...")
    data["womenMatches"] = fetch_team_matches(3521, season, is_women=True)
    print(
        f"    Loaded {len(data['womenMatches']['upcoming'])} upcoming and {len(data['womenMatches']['recent'])} recent Women's matches."
    )

    # 3. Top Scorers, Assists & Team Statistics
    print(" -> Fetching Men's player & team statistics (/api/v1/teams/5/statistics)...")
    men_scorers, men_team_stats = fetch_team_stats_and_scorers(5, season, is_women=False, existing_scorers=data.get("topScorers"))
    data["topScorers"] = men_scorers
    data["teamStats"] = men_team_stats
    print(f"    Loaded {len(men_scorers)} Men's top scorers and {len(men_team_stats.get('topAssists', []))} assist leaders.")

    print(" -> Fetching Women's player & team statistics (/api/v1/teams/3521/statistics)...")
    women_scorers, women_team_stats = fetch_team_stats_and_scorers(3521, season, is_women=True, existing_scorers=data.get("womenTopScorers"))
    data["womenTopScorers"] = women_scorers
    data["womenTeamStats"] = women_team_stats
    print(f"    Loaded {len(women_scorers)} Women's top scorers.")

    # 4. Metadata
    data["metadata"] = {
        "source": "Eesti Jalgpalli Liit (EJL OpenData API v1)",
        "sourceUrl": "https://opendata.jalgpall.ee/api/v1",
        "lastUpdated": datetime.now(TALLINN_TZ).isoformat(),
        "season": str(season),
        "status": "success",
    }

    DATA_FILE.write_text(json.dumps(data, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(f"Successfully updated {DATA_FILE} via EJL OpenData API!")


if __name__ == "__main__":
    main()
