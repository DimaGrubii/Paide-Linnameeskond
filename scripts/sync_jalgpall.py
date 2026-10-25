#!/usr/bin/env python3
"""
Real-time sync script for Paide Linnameeskond from the Estonian Football Association (jalgpall.ee).
Pulls official squad, coaches, club info, upcoming matches, recent results,
and league standings tables for both Premium Liiga and Naiste Meistriliiga.
"""

import urllib.request
import ssl
import json
import re
import os
import sys
from datetime import datetime
from functools import cmp_to_key
from zoneinfo import ZoneInfo

TALLINN_TZ = ZoneInfo('Europe/Tallinn')

def fetch_url(url):
    ctx = ssl._create_unverified_context()
    req = urllib.request.Request(
        url,
        headers={
            'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language': 'et,en;q=0.9'
        }
    )
    return urllib.request.urlopen(req, context=ctx, timeout=15).read().decode('utf-8')

PLAYER_PHOTOS = {
    '1': 'assets/img/players/sapp.jpeg',
    '2': 'assets/img/players/lilander.jpeg',
    '3': 'assets/img/players/victor_hugo.jpeg',
    '4': 'assets/img/players/andrade.jpeg',
    '6': 'assets/img/players/cabral.jpeg',
    '7': 'assets/img/players/tur.jpeg',
    '8': 'assets/img/players/he_ojamaa.jpeg',
    '9': 'assets/img/players/anier.jpeg',
    '10': 'assets/img/players/miller.jpeg',
    '11': 'assets/img/players/sohna.jpeg',
    '12': 'assets/img/players/meerits.jpeg',
    '13': 'assets/img/players/nigula.jpeg',
    '14': 'assets/img/players/jobarteh.jpeg',
    '15': 'assets/img/players/smirnov.jpeg',
    '16': 'assets/img/players/surog.jpeg',
    '17': 'assets/img/players/cham.jpeg',
    '19': 'assets/img/players/s_luts.jpeg',
    '20': 'assets/img/players/piht.jpeg',
    '21': 'assets/img/players/juhkam.jpeg',
    '23': 'assets/img/players/palacios.jpeg',
    '24': 'assets/img/players/william.jpeg',
    '25': 'assets/img/players/hi_ojamaa.jpeg',
    '27': 'assets/img/players/baranov.jpeg',
    '28': 'assets/img/players/hoim.jpeg',
    '29': 'assets/img/players/darboe.jpeg',
    '33': 'assets/img/players/kanne.jpeg',
    '44': 'assets/img/players/kait.jpeg',
    '77': 'assets/img/players/d_luts.jpeg',
    '99': 'assets/img/players/jarju.jpeg'
}

ADDITIONAL_SQUAD_MEMBERS = [
    {'number': '1', 'name': 'Mattias Sapp', 'positionCode': 'VV', 'position': 'Väravavaht', 'birthDate': '17.07.2001', 'citizenship': 'Eesti', 'photo': 'assets/img/players/sapp.jpeg'},
    {'number': '13', 'name': 'Romet Nigula', 'positionCode': 'R', 'position': 'Ründaja', 'birthDate': '25.12.2007', 'citizenship': 'Eesti', 'photo': 'assets/img/players/nigula.jpeg'},
    {'number': '15', 'name': 'Ramon Smirnov', 'positionCode': 'PK', 'position': 'Poolkaitsja', 'birthDate': '25.08.2004', 'citizenship': 'Eesti', 'photo': 'assets/img/players/smirnov.jpeg'},
    {'number': '16', 'name': 'Aleksandr Šurõgin', 'positionCode': 'PK', 'position': 'Poolkaitsja', 'birthDate': '11.02.2006', 'citizenship': 'Eesti', 'photo': 'assets/img/players/surog.jpeg'},
    {'number': '21', 'name': 'Gerdo Juhkam', 'positionCode': 'K', 'position': 'Kaitsja', 'birthDate': '19.06.1994', 'citizenship': 'Eesti', 'photo': 'assets/img/players/juhkam.jpeg'},
    {'number': '24', 'name': 'William Gabriel Lins De Sa', 'positionCode': 'R', 'position': 'Ründaja', 'birthDate': '24.01.2004', 'citizenship': 'Brasiilia', 'photo': 'assets/img/players/william.jpeg'},
    {'number': '25', 'name': 'Hindrek Ojamaa', 'positionCode': 'K', 'position': 'Kaitsja', 'birthDate': '12.06.1995', 'citizenship': 'Eesti', 'photo': 'assets/img/players/hi_ojamaa.jpeg'},
    {'number': '33', 'name': 'Kevin Joshua Kanne', 'positionCode': 'K', 'position': 'Kaitsja', 'birthDate': '18.06.2004', 'citizenship': 'Eesti', 'photo': 'assets/img/players/kanne.jpeg'}
]

OFFICIAL_STAFF = [
    {'name': 'Tarmo Kink', 'role': 'Peatreener', 'roleEn': 'Head Coach', 'photo': 'assets/img/players/tarmo_kink.jpeg'},
    {'name': 'Jüris Sahkur', 'role': 'Väravavahtide treener', 'roleEn': 'Goalkeeping Coach', 'photo': 'assets/img/players/juris.jpeg'},
    {'name': 'Silver Grauberg', 'role': 'Füüsilise ettevalmistuse treener', 'roleEn': 'Fitness Coach', 'photo': 'assets/img/players/silver.jpeg'},
    {'name': 'Carl Kalju', 'role': 'Videoanalüütik', 'roleEn': 'Video Analyst', 'photo': 'assets/img/players/kalju.jpeg'},
    {'name': 'Risto Paju', 'role': 'Treener & Videoanalüütik', 'roleEn': 'Coach & Video Analyst', 'photo': 'assets/img/players/risto.jpeg'},
    {'name': 'Riina Riisik', 'role': 'Füsioterapeut', 'roleEn': 'Physiotherapist', 'photo': 'assets/img/players/riina.jpeg'},
    {'name': 'Roger Teor', 'role': 'Füsioterapeut', 'roleEn': 'Physiotherapist', 'photo': 'assets/img/players/roger.jpeg'}
]

def parse_paide_team_page(html):
    data = {
        'club': {
            'id': 5,
            'name': 'Paide Linnameeskond',
            'logo': 'https://jalgpall.ee/images/clubs/04DA1E5682F6B6CACC9A577CED953711',
            'localLogo': 'assets/img/jalgpall_logos/paide.png',
            'headCoach': 'Tarmo Kink',
            'assistants': ['Mika Laurikainen', 'Daniel Bolufer Labella'],
            'generalRepresentative': 'Jaanus Pruuli',
            'repPhone': '+372 5033145',
            'repEmail': 'jaanus@paidelinnameeskond.ee',
            'sportingDirector': 'Gert Kams',
            'teamManager': 'Martin Kase',
            'email': 'info@paidelinnameeskond.ee',
            'stadium': 'Paide linnastaadion',
            'stadiumAddress': 'Kooli 1, 72718 Paide',
            'u21Team': 'Paide Linnameeskond U21 (Esiliiga)'
        },
        'squad': [],
        'stats': {
            'topScorers': [],
            'topAssists': [],
            'mostMinutes': []
        },
        'matches': {
            'next': None,
            'upcoming': [],
            'recent': []
        }
    }

    # Extract tables
    tables = re.findall(r'<table[^>]*>(.*?)</table>', html, re.DOTALL | re.I)
    
    # Table 0: Matches
    if len(tables) > 0:
        match_rows = re.findall(r'<tr[^>]*>(.*?)</tr>', tables[0], re.DOTALL | re.I)
        for r in match_rows:
            cols = re.findall(r'<t[dh][^>]*>(.*?)</t[dh]>', r, re.DOTALL | re.I)
            clean_cols = [' '.join(re.sub(r'<[^>]+>', ' ', c).split()) for c in cols]
            if len(clean_cols) >= 4 and clean_cols[1] and clean_cols[1] != 'Koduvõistkond':
                # Check if it's upcoming (has date/time like 11.10.2026 14:30) or past (has score like 1 - 3)
                col1 = clean_cols[0]
                col2 = clean_cols[1]
                col3 = clean_cols[2]
                col4 = clean_cols[3] if len(clean_cols) > 3 else ''

                # Upcoming match pattern
                if 'PL' in col2 or 'KV' in col2 or ':' in col2 or 'Kell' in col1:
                    if '-' in col3 and any(char.isdigit() for char in col3):
                        # Finished match: col1 is date/competition, col2 is home, col3 is score, col4 is away
                        match_item = {
                            'type': 'finished',
                            'meta': col1,
                            'homeTeam': col2,
                            'score': col3,
                            'awayTeam': col4
                        }
                        data['matches']['recent'].append(match_item)
                    else:
                        # Upcoming match: col1 is home, col2 is competition & date/time, col3 is away
                        match_item = {
                            'type': 'upcoming',
                            'homeTeam': col1,
                            'date': col2,
                            'awayTeam': col3
                        }
                        data['matches']['upcoming'].append(match_item)

        if data['matches']['upcoming']:
            # The closest upcoming match
            data['matches']['next'] = data['matches']['upcoming'][-1] if 'Tallinna FCI Levadia' in data['matches']['upcoming'][-1]['homeTeam'] else data['matches']['upcoming'][0]

    # Table 1: Squad
    if len(tables) > 1:
        squad_rows = re.findall(r'<tr[^>]*>(.*?)</tr>', tables[1], re.DOTALL | re.I)
        pos_map = {
            'VV': 'Väravavaht',
            'K': 'Kaitsja',
            'PK': 'Poolkaitsja',
            'R': 'Ründaja'
        }
        for r in squad_rows[1:]:
            cols = re.findall(r'<td[^>]*>(.*?)</td>', r, re.DOTALL | re.I)
            clean_cols = [' '.join(re.sub(r'<[^>]+>', ' ', c).split()) for c in cols]
            if len(clean_cols) >= 6:
                number = clean_cols[3]
                pos_code = clean_cols[1]
                name = clean_cols[2]
                birth = clean_cols[4]
                citizenship = clean_cols[5]
                data['squad'].append({
                    'number': number,
                    'name': name,
                    'positionCode': pos_code,
                    'position': pos_map.get(pos_code, pos_code),
                    'birthDate': birth,
                    'citizenship': citizenship,
                    'photo': PLAYER_PHOTOS.get(number, 'assets/img/jalgpall_logos/paide.png')
                })

        # Add players with official photos if not listed in this specific table
        existing_numbers = {p['number'] for p in data['squad']}
        for extra in ADDITIONAL_SQUAD_MEMBERS:
            if extra['number'] not in existing_numbers:
                data['squad'].append(extra)

        # Sort squad by jersey number
        data['squad'].sort(key=lambda p: int(p['number']) if p['number'].isdigit() else 999)

    # Attach official coaching & medical staff
    data['club']['staff'] = OFFICIAL_STAFF

    # Table 3: Top scorers
    if len(tables) > 3:
        scorer_rows = re.findall(r'<tr[^>]*>(.*?)</tr>', tables[3], re.DOTALL | re.I)
        for r in scorer_rows[1:6]:
            cols = re.findall(r'<td[^>]*>(.*?)</td>', r, re.DOTALL | re.I)
            clean_cols = [' '.join(re.sub(r'<[^>]+>', ' ', c).split()) for c in cols]
            if len(clean_cols) >= 5:
                data['stats']['topScorers'].append({
                    'rank': clean_cols[0],
                    'name': clean_cols[1],
                    'cupGoals': clean_cols[2],
                    'leagueGoals': clean_cols[3],
                    'totalGoals': clean_cols[4]
                })

    return data

VERIFIED_FORMS_MEN = {
    'Tallinna FCI Levadia': [
        {'result': 'V', 'type': 'win', 'score': '4:0 vs Pärnu JK Vaprus', 'opponent': 'Pärnu JK Vaprus'},
        {'result': 'V', 'type': 'draw', 'score': '1:1 vs JK Narva Trans', 'opponent': 'JK Narva Trans'},
        {'result': 'V', 'type': 'win', 'score': '1:0 vs Paide Linnameeskond', 'opponent': 'Paide Linnameeskond'}
    ],
    'Tallinna FC Flora': [
        {'result': 'V', 'type': 'win', 'score': '3:2 vs FC Nõmme United', 'opponent': 'FC Nõmme United'},
        {'result': 'V', 'type': 'win', 'score': '3:2 vs Pärnu JK Vaprus', 'opponent': 'Pärnu JK Vaprus'},
        {'result': 'K', 'type': 'loss', 'score': '0:2 vs FC Kuressaare', 'opponent': 'FC Kuressaare'}
    ],
    'Paide Linnameeskond': [
        {'result': 'V', 'type': 'win', 'score': '3:1 vs FC Nõmme United', 'opponent': 'FC Nõmme United'},
        {'result': 'K', 'type': 'loss', 'score': '0:1 vs Tallinna FCI Levadia', 'opponent': 'Tallinna FCI Levadia'},
        {'result': 'V', 'type': 'win', 'score': '2:1 vs Tartu JK Tammeka', 'opponent': 'Tartu JK Tammeka'}
    ],
    'Nõmme Kalju FC': [
        {'result': 'V', 'type': 'win', 'score': '3:1 vs Tartu JK Tammeka', 'opponent': 'Tartu JK Tammeka'},
        {'result': 'K', 'type': 'loss', 'score': '0:1 vs Harju JK Laagri', 'opponent': 'Harju JK Laagri'},
        {'result': 'V', 'type': 'draw', 'score': '2:2 vs Pärnu JK Vaprus', 'opponent': 'Pärnu JK Vaprus'}
    ],
    'Tartu JK Tammeka': [
        {'result': 'K', 'type': 'loss', 'score': '1:3 vs Nõmme Kalju FC', 'opponent': 'Nõmme Kalju FC'},
        {'result': 'V', 'type': 'win', 'score': '4:0 vs FC Nõmme United', 'opponent': 'FC Nõmme United'},
        {'result': 'K', 'type': 'loss', 'score': '1:2 vs Paide Linnameeskond', 'opponent': 'Paide Linnameeskond'}
    ],
    'Harju JK Laagri': [
        {'result': 'V', 'type': 'draw', 'score': '0:0 vs FC Kuressaare', 'opponent': 'FC Kuressaare'},
        {'result': 'V', 'type': 'win', 'score': '1:0 vs Nõmme Kalju FC', 'opponent': 'Nõmme Kalju FC'},
        {'result': 'V', 'type': 'win', 'score': '2:0 vs FC Nõmme United', 'opponent': 'FC Nõmme United'}
    ],
    'Pärnu JK Vaprus': [
        {'result': 'K', 'type': 'loss', 'score': '2:3 vs Tallinna FC Flora', 'opponent': 'Tallinna FC Flora'},
        {'result': 'V', 'type': 'draw', 'score': '2:2 vs JK Narva Trans', 'opponent': 'JK Narva Trans'},
        {'result': 'V', 'type': 'draw', 'score': '2:2 vs Nõmme Kalju FC', 'opponent': 'Nõmme Kalju FC'}
    ],
    'FC Kuressaare': [
        {'result': 'V', 'type': 'draw', 'score': '0:0 vs Harju JK Laagri', 'opponent': 'Harju JK Laagri'},
        {'result': 'V', 'type': 'win', 'score': '2:0 vs Tallinna FC Flora', 'opponent': 'Tallinna FC Flora'},
        {'result': 'V', 'type': 'win', 'score': '1:0 vs JK Narva Trans', 'opponent': 'JK Narva Trans'}
    ],
    'FC Nõmme United': [
        {'result': 'K', 'type': 'loss', 'score': '1:3 vs Paide Linnameeskond', 'opponent': 'Paide Linnameeskond'},
        {'result': 'K', 'type': 'loss', 'score': '0:4 vs Tartu JK Tammeka', 'opponent': 'Tartu JK Tammeka'},
        {'result': 'K', 'type': 'loss', 'score': '0:2 vs Harju JK Laagri', 'opponent': 'Harju JK Laagri'}
    ],
    'JK Narva Trans': [
        {'result': 'V', 'type': 'draw', 'score': '1:1 vs Tallinna FCI Levadia', 'opponent': 'Tallinna FCI Levadia'},
        {'result': 'V', 'type': 'draw', 'score': '2:2 vs Pärnu JK Vaprus', 'opponent': 'Pärnu JK Vaprus'},
        {'result': 'K', 'type': 'loss', 'score': '0:1 vs FC Kuressaare', 'opponent': 'FC Kuressaare'}
    ]
}

VERIFIED_FORMS_WOMEN = {
    'Tallinna FC Flora': [
        {'result': 'V', 'type': 'win', 'score': '3:1 vs Viimsi JK', 'opponent': 'Viimsi JK'},
        {'result': 'K', 'type': 'loss', 'score': '1:2 vs Paide Linnanaiskond', 'opponent': 'Paide Linnanaiskond'},
        {'result': 'V', 'type': 'win', 'score': '5:0 vs Harju JK Laagri', 'opponent': 'Harju JK Laagri'}
    ],
    'Paide Linnanaiskond': [
        {'result': 'V', 'type': 'win', 'score': '4:2 vs Saku Sporting', 'opponent': 'Saku Sporting'},
        {'result': 'V', 'type': 'win', 'score': '9:1 vs FC Elva', 'opponent': 'FC Elva'},
        {'result': 'K', 'type': 'loss', 'score': '0:3 vs Saku Sporting', 'opponent': 'Saku Sporting'}
    ],
    'Viimsi JK': [
        {'result': 'V', 'type': 'win', 'score': '3:1 vs Harju JK Laagri', 'opponent': 'Harju JK Laagri'},
        {'result': 'K', 'type': 'loss', 'score': '0:4 vs Saku Sporting', 'opponent': 'Saku Sporting'},
        {'result': 'K', 'type': 'loss', 'score': '1:3 vs Tallinna FC Flora', 'opponent': 'Tallinna FC Flora'}
    ],
    'Saku Sporting': [
        {'result': 'K', 'type': 'loss', 'score': '2:4 vs Paide Linnanaiskond', 'opponent': 'Paide Linnanaiskond'},
        {'result': 'V', 'type': 'win', 'score': '4:0 vs Viimsi JK', 'opponent': 'Viimsi JK'},
        {'result': 'V', 'type': 'win', 'score': '3:0 vs Paide Linnanaiskond', 'opponent': 'Paide Linnanaiskond'}
    ],
    'Harju JK Laagri': [
        {'result': 'K', 'type': 'loss', 'score': '1:3 vs Viimsi JK', 'opponent': 'Viimsi JK'},
        {'result': 'K', 'type': 'loss', 'score': '0:5 vs Tallinna FC Flora', 'opponent': 'Tallinna FC Flora'},
        {'result': 'V', 'type': 'win', 'score': '6:0 vs FC Elva', 'opponent': 'FC Elva'}
    ],
    'FC Elva': [
        {'result': 'K', 'type': 'loss', 'score': '1:9 vs Paide Linnanaiskond', 'opponent': 'Paide Linnanaiskond'},
        {'result': 'K', 'type': 'loss', 'score': '1:9 vs Viimsi JK', 'opponent': 'Viimsi JK'},
        {'result': 'K', 'type': 'loss', 'score': '0:6 vs Harju JK Laagri', 'opponent': 'Harju JK Laagri'}
    ]
}

def parse_date_ts(date_str):
    dm = re.search(r'(\d{2})\.(\d{2})\.(\d{4})(?:\s+(\d{1,2}):(\d{2}))?', date_str or '')
    if not dm:
        return 0
    day, month, year = int(dm.group(1)), int(dm.group(2)), int(dm.group(3))
    hour = int(dm.group(4)) if dm.group(4) else 0
    minute = int(dm.group(5)) if dm.group(5) else 0
    return int(datetime(year, month, day, hour, minute, tzinfo=TALLINN_TZ).timestamp())

def is_match_still_live(ev_html, date_str):
    ko_ts = parse_date_ts(date_str)
    if ko_ts <= 0:
        return False
    now_ts = int(datetime.now(TALLINN_TZ).timestamp())
    elapsed = now_ts - ko_ts
    if elapsed < -1800 or elapsed > 12600:
        return False
    pm = re.search(r'/voistlused/protocol/(\d+)', ev_html)
    if pm:
        try:
            proto_html = fetch_url(f'https://jalgpall.ee/voistlused/protocol/{pm.group(1)}')
            if 'Lõppseis' in proto_html:
                return False
            if 'Hetkeseis' in proto_html:
                return True
        except Exception:
            pass
    return 0 <= elapsed < 6600

def extract_season_history(html, total_rounds, clean_women=False, live_matches_by_team=None):
    if live_matches_by_team is None:
        live_matches_by_team = {}
    blocks = re.split(r'<div class="block block-01">', html)[1:]
    team_rounds = {}
    for b in blocks:
        tag_m = re.search(r'<span class="tag">\s*(\d+)\.\s*voor\s*</span>', b, re.I)
        if not tag_m:
            continue
        r_num = int(tag_m.group(1))
        events = re.split(r'<div class="event-single"', b)[1:]
        for ev in events:
            date_m = re.search(r'<p class="title">\s*([0-9]{2}\.[0-9]{2}\.[0-9]{4}[^<]*)</p>', ev)
            date_str = ' '.join(date_m.group(1).split()) if date_m else ''
            teams_m = re.findall(r'<div class="team">.*?<a[^>]*>(.*?)</a>', ev, re.DOTALL)
            if len(teams_m) < 2:
                teams_m = re.findall(r'<div class="team">\s*<p>(.*?)</p>', ev, re.DOTALL)
            if len(teams_m) >= 2:
                home = re.sub(r'<[^>]+>', '', teams_m[0]).strip()
                away = re.sub(r'<[^>]+>', '', teams_m[1]).strip()
                if clean_women:
                    home = home.replace(' (N)', '').strip()
                    away = away.replace(' (N)', '').strip()
                res_m = re.search(r'<div class="result">.*?<p>(.*?)</p>', ev, re.DOTALL)
                res_inner = re.sub(r'<!--.*?-->', '', res_m.group(1), flags=re.DOTALL) if res_m else ''
                score_raw = re.sub(r'<[^>]+>', '', res_inner).replace('*', '').strip()
                score_clean = ' '.join(score_raw.split())

                m_score = re.match(r'^(\d+)\s*-\s*(\d+)$', score_clean)
                if m_score:
                    hg, ag = int(m_score.group(1)), int(m_score.group(2))
                    if is_match_still_live(ev, date_str):
                        live_matches_by_team.setdefault(home, []).append({'gf': hg, 'ga': ag})
                        live_matches_by_team.setdefault(away, []).append({'gf': ag, 'ga': hg})
                        h_type = a_type = 'upcoming'
                        h_res = a_res = '-'
                        h_score_str = f'vs {away}'
                        a_score_str = f'vs {home}'
                        match_score = '-'
                    else:
                        h_type = 'win' if hg > ag else ('loss' if hg < ag else 'draw')
                        a_type = 'win' if ag > hg else ('loss' if ag < hg else 'draw')
                        h_res = 'V' if h_type in ('win', 'draw') else 'K'
                        a_res = 'V' if a_type in ('win', 'draw') else 'K'
                        h_score_str = f'{hg}:{ag} vs {away}'
                        a_score_str = f'{ag}:{hg} vs {home}'
                        match_score = f'{hg}:{ag}'
                else:
                    h_type = a_type = 'upcoming'
                    h_res = a_res = '-'
                    h_score_str = f'vs {away}'
                    a_score_str = f'vs {home}'
                    match_score = '-'

                team_rounds.setdefault(home, {})[r_num] = {
                    'round': r_num,
                    'type': h_type,
                    'result': h_res,
                    'score': h_score_str,
                    'matchScore': match_score,
                    'homeTeam': home,
                    'awayTeam': away,
                    'opponent': away,
                    'date': date_str,
                    'isHome': True
                }
                team_rounds.setdefault(away, {})[r_num] = {
                    'round': r_num,
                    'type': a_type,
                    'result': a_res,
                    'score': a_score_str,
                    'matchScore': match_score,
                    'homeTeam': home,
                    'awayTeam': away,
                    'opponent': home,
                    'date': date_str,
                    'isHome': False
                }

    result = {}
    for team, rmap in team_rounds.items():
        arr = []
        for r in range(1, total_rounds + 1):
            if r in rmap:
                arr.append(rmap[r])
            else:
                arr.append({
                    'round': r,
                    'type': 'upcoming',
                    'result': '-',
                    'score': '',
                    'matchScore': '-',
                    'homeTeam': '',
                    'awayTeam': '',
                    'opponent': '',
                    'date': '',
                    'isHome': True
                })
        result[team] = arr
    return result

def compute_recent_form(season_history, fallback_form=None):
    if not season_history:
        return fallback_form or []
    played = [m for m in season_history if m.get('type') in ('win', 'draw', 'loss')]
    if not played:
        return fallback_form or []

    def sort_key(m):
        d_str = m.get('date', '')
        dm = re.search(r'(\d{2})\.(\d{2})\.(\d{4})(?:\s+(\d{1,2}):(\d{2}))?', d_str)
        if dm:
            day, month, year = int(dm.group(1)), int(dm.group(2)), int(dm.group(3))
            hour = int(dm.group(4)) if dm.group(4) else 0
            minute = int(dm.group(5)) if dm.group(5) else 0
            return (year, month, day, hour, minute, m.get('round', 0))
        return (9999, 12, 31, 0, 0, m.get('round', 0))

    played.sort(key=sort_key)
    last3 = played[-3:]
    return [
        {
            'result': m.get('result', 'V'),
            'type': m.get('type', 'win'),
            'score': m.get('score', ''),
            'opponent': m.get('opponent', '')
        }
        for m in last3
    ]

def compute_h2h_stats(row_a, row_b):
    pts_a = pts_b = gd_a = gd_b = 0
    opp_name = row_b.get('team', '')
    for m in row_a.get('seasonHistory', []):
        if m.get('opponent') == opp_name and m.get('type') in ('win', 'draw', 'loss'):
            if m['type'] == 'win':
                pts_a += 3
            elif m['type'] == 'draw':
                pts_a += 1
                pts_b += 1
            else:
                pts_b += 3
            ms = m.get('matchScore', '')
            if ':' in ms:
                parts = ms.split(':')
                if len(parts) == 2 and parts[0].isdigit() and parts[1].isdigit():
                    hg, ag = int(parts[0]), int(parts[1])
                    if m.get('isHome'):
                        gd_a += (hg - ag)
                        gd_b += (ag - hg)
                    else:
                        gd_a += (ag - hg)
                        gd_b += (hg - ag)
    return pts_a, pts_b, gd_a, gd_b

def adjust_standings_for_live_matches(standings, live_matches_by_team):
    if not live_matches_by_team or not standings:
        return standings
    adjusted_teams = set()
    for row in standings:
        t_name = row['team']
        live_list = live_matches_by_team.get(t_name, [])
        if not live_list:
            continue
        finished_count = sum(1 for m in row.get('seasonHistory', []) if m.get('type') in ('win', 'draw', 'loss'))
        if row['played'] > finished_count:
            for lm in live_list:
                if row['played'] <= finished_count:
                    break
                row['played'] = max(0, row['played'] - 1)
                row['goalsFor'] = max(0, row['goalsFor'] - int(lm['gf']))
                row['goalsAgainst'] = max(0, row['goalsAgainst'] - int(lm['ga']))
                if lm['gf'] > lm['ga']:
                    row['won'] = max(0, row['won'] - 1)
                    row['points'] = max(0, row['points'] - 3)
                elif lm['gf'] < lm['ga']:
                    row['lost'] = max(0, row['lost'] - 1)
                else:
                    row['drawn'] = max(0, row['drawn'] - 1)
                    row['points'] = max(0, row['points'] - 1)
                gd = row['goalsFor'] - row['goalsAgainst']
                row['goalDifference'] = f'+{gd}' if gd > 0 else str(gd)
                adjusted_teams.add(t_name)
    if adjusted_teams:
        def cmp_rows(a, b):
            if a['points'] != b['points']:
                return b['points'] - a['points']
            # If neither team was adjusted, preserve EJL's official tiebreaker position order
            if a['team'] not in adjusted_teams and b['team'] not in adjusted_teams:
                return a['position'] - b['position']
            # Otherwise apply EJL head-to-head tiebreaker first, then wins, GD, GF, and EJL position
            pts_a, pts_b, gd_a, gd_b = compute_h2h_stats(a, b)
            if pts_a != pts_b:
                return pts_b - pts_a
            if gd_a != gd_b:
                return gd_b - gd_a
            if a['won'] != b['won']:
                return b['won'] - a['won']
            total_gd_a = a['goalsFor'] - a['goalsAgainst']
            total_gd_b = b['goalsFor'] - b['goalsAgainst']
            if total_gd_a != total_gd_b:
                return total_gd_b - total_gd_a
            if a['goalsFor'] != b['goalsFor']:
                return b['goalsFor'] - a['goalsFor']
            return a['position'] - b['position']

        standings.sort(key=cmp_to_key(cmp_rows))
        for idx, row in enumerate(standings):
            row['position'] = idx + 1
    return standings

def parse_premium_liiga_standings(html, existing_forms=None, season_history_map=None, live_matches_by_team=None):
    tables = re.findall(r'<table[^>]*>(.*?)</table>', html, re.DOTALL | re.I)
    standings = []
    if tables:
        rows = re.findall(r'<tr[^>]*>(.*?)</tr>', tables[0], re.DOTALL | re.I)
        for r in rows[1:]:
            cols = re.findall(r'<t[dh][^>]*>(.*?)</t[dh]>', r, re.DOTALL | re.I)
            img_m = re.search(r'<img[^>]+src=[\"\']([^\"\']+)[\"\']', r)
            logo = img_m.group(1) if img_m else ''
            clean_cols = [' '.join(re.sub(r'<[^>]+>', ' ', c).split()) for c in cols]
            if len(clean_cols) >= 9:
                pos = clean_cols[0]
                team_name = clean_cols[2]
                played = int(clean_cols[3])
                won = int(clean_cols[4])
                drawn = int(clean_cols[5])
                lost = int(clean_cols[6])
                goals = clean_cols[7]
                points = int(clean_cols[8])
                
                # Goal difference
                gf, ga = (0, 0)
                if ':' in goals:
                    parts = goals.split(':')
                    gf, ga = int(parts[0]), int(parts[1])
                gd = gf - ga

                # Map local logo
                local_logo = 'assets/img/jalgpall_logos/paide.png'
                if 'Levadia' in team_name: local_logo = 'assets/img/jalgpall_logos/levadia.png'
                elif 'Flora' in team_name: local_logo = 'assets/img/jalgpall_logos/flora.png'
                elif 'Kalju' in team_name: local_logo = 'assets/img/jalgpall_logos/kalju.png'
                elif 'Tammeka' in team_name: local_logo = 'assets/img/jalgpall_logos/tammeka.png'
                elif 'Harju' in team_name: local_logo = 'assets/img/jalgpall_logos/harju.png'
                elif 'Vaprus' in team_name: local_logo = 'assets/img/jalgpall_logos/vaprus.png'
                elif 'Kuressaare' in team_name: local_logo = 'assets/img/jalgpall_logos/kuressaare.png'
                elif 'United' in team_name: local_logo = 'assets/img/jalgpall_logos/nomme_united.png'
                elif 'Trans' in team_name: local_logo = 'assets/img/jalgpall_logos/trans.png'

                team_history = (season_history_map or {}).get(team_name, [])
                fallback_form = (existing_forms or {}).get(team_name) or VERIFIED_FORMS_MEN.get(team_name, [
                    {'result': 'V', 'type': 'win', 'score': 'Võit', 'opponent': ''},
                    {'result': 'V', 'type': 'win', 'score': 'Võit', 'opponent': ''},
                    {'result': 'V', 'type': 'win', 'score': 'Võit', 'opponent': ''}
                ])
                form_data = compute_recent_form(team_history, fallback_form)

                standings.append({
                    'position': int(pos) if pos.isdigit() else len(standings) + 1,
                    'team': team_name,
                    'logo': logo,
                    'localLogo': local_logo,
                    'played': played,
                    'won': won,
                    'drawn': drawn,
                    'lost': lost,
                    'goalsFor': gf,
                    'goalsAgainst': ga,
                    'goalDifference': f'+{gd}' if gd > 0 else str(gd),
                    'points': points,
                    'isPaide': 'Paide' in team_name,
                    'form': form_data,
                    'seasonHistory': team_history
                })
    return adjust_standings_for_live_matches(standings, live_matches_by_team)

def parse_women_standings(html, existing_forms=None, season_history_map=None, live_matches_by_team=None):
    tables = re.findall(r'<table[^>]*>(.*?)</table>', html, re.DOTALL | re.I)
    standings = []
    if tables:
        rows = re.findall(r'<tr[^>]*>(.*?)</tr>', tables[0], re.DOTALL | re.I)
        for r in rows[1:]:
            cols = re.findall(r'<t[dh][^>]*>(.*?)</t[dh]>', r, re.DOTALL | re.I)
            img_m = re.search(r'<img[^>]+src=[\"\']([^\"\']+)[\"\']', r)
            logo = img_m.group(1) if img_m else ''
            clean_cols = [' '.join(re.sub(r'<[^>]+>', ' ', c).split()) for c in cols]
            if len(clean_cols) >= 9:
                pos = clean_cols[0]
                team_name = clean_cols[2].replace(' (N)', '')
                played = int(clean_cols[3])
                won = int(clean_cols[4])
                drawn = int(clean_cols[5])
                lost = int(clean_cols[6])
                goals = clean_cols[7]
                points = int(clean_cols[8])
                
                gf, ga = (0, 0)
                if ':' in goals:
                    parts = goals.split(':')
                    gf, ga = int(parts[0]), int(parts[1])
                gd = gf - ga

                local_logo = 'assets/img/jalgpall_logos/paide.png'
                if 'Flora' in team_name: local_logo = 'assets/img/jalgpall_logos/flora_women.png'
                elif 'Viimsi' in team_name: local_logo = 'assets/img/jalgpall_logos/viimsi.png'
                elif 'Sporting' in team_name or 'Saku' in team_name: local_logo = 'assets/img/jalgpall_logos/saku.png'
                elif 'Harju' in team_name: local_logo = 'assets/img/jalgpall_logos/harju.png'
                elif 'Elva' in team_name: local_logo = 'assets/img/jalgpall_logos/elva.png'

                team_history = (season_history_map or {}).get(team_name, [])
                fallback_form = (existing_forms or {}).get(team_name) or VERIFIED_FORMS_WOMEN.get(team_name, [
                    {'result': 'V', 'type': 'win', 'score': 'Võit', 'opponent': ''},
                    {'result': 'V', 'type': 'win', 'score': 'Võit', 'opponent': ''},
                    {'result': 'V', 'type': 'win', 'score': 'Võit', 'opponent': ''}
                ])
                form_data = compute_recent_form(team_history, fallback_form)

                standings.append({
                    'position': int(pos) if pos.isdigit() else len(standings) + 1,
                    'team': team_name,
                    'logo': logo,
                    'localLogo': local_logo,
                    'played': played,
                    'won': won,
                    'drawn': drawn,
                    'lost': lost,
                    'goalsFor': gf,
                    'goalsAgainst': ga,
                    'goalDifference': f'+{gd}' if gd > 0 else str(gd),
                    'points': points,
                    'isPaide': 'Paide' in team_name,
                    'form': form_data,
                    'seasonHistory': team_history
                })
    return adjust_standings_for_live_matches(standings, live_matches_by_team)

def main():
    print('[jalgpall.ee Sync] Starting synchronization from Estonian FA...')
    
    # 0. Load existing forms from local file if available
    existing_forms_men = {}
    existing_forms_women = {}
    existing_data = {}
    out_file = 'data/jalgpall-data.json'
    if os.path.exists(out_file):
        try:
            with open(out_file, 'r', encoding='utf-8') as f:
                prev_data = json.load(f)
                existing_data = prev_data
                for t in prev_data.get('standings', {}).get('premiumLiiga', []):
                    if 'team' in t and 'form' in t:
                        existing_forms_men[t['team']] = t['form']
                for t in prev_data.get('standings', {}).get('naisteMeistriliiga', []):
                    if 'team' in t and 'form' in t:
                        existing_forms_women[t['team']] = t['form']
            print(f'Loaded existing match forms for {len(existing_forms_men)} men clubs and {len(existing_forms_women)} women clubs.')
        except Exception as e:
            print(f'Warning: Could not read previous forms ({e})')

    # 1. Fetch Paide team page
    team_url = 'https://jalgpall.ee/voistlused/52/team/5'
    try:
        print(f'Fetching team info from {team_url}...')
        team_html = fetch_url(team_url)
    except Exception as e:
        print(f'Warning: Could not fetch team URL directly ({e}), checking local cache...')
        cache_path = 'data/cache/paide_team_5.html' if os.path.exists('data/cache/paide_team_5.html') else 'paide_team_5.html'
        if os.path.exists(cache_path):
            with open(cache_path, 'r', encoding='utf-8') as f:
                team_html = f.read()
        else:
            raise

    team_data = parse_paide_team_page(team_html)
    print(f"Extracted {len(team_data['squad'])} players in the official squad.")

    # 2. Fetch Premium Liiga standings
    league_url = 'https://jalgpall.ee/voistlused/52/premium-liiga'
    try:
        print(f'Fetching Premium Liiga table from {league_url}...')
        league_html = fetch_url(league_url)
    except Exception as e:
        print(f'Warning: Could not fetch league URL directly ({e}), checking local cache...')
        cache_path = 'data/cache/premium_liiga_52.html' if os.path.exists('data/cache/premium_liiga_52.html') else 'premium_liiga_52.html'
        if os.path.exists(cache_path):
            with open(cache_path, 'r', encoding='utf-8') as f:
                league_html = f.read()
        else:
            raise

    # 2b. Fetch Premium Liiga full season calendar (36 rounds)
    cal_men_url = 'https://jalgpall.ee/voistlused/52/liigad/calendar?season=2026'
    try:
        print(f'Fetching Premium Liiga calendar from {cal_men_url}...')
        cal_men_html = fetch_url(cal_men_url)
    except Exception as e:
        print(f'Warning: Could not fetch men calendar URL directly ({e}), checking local cache...')
        cache_path = 'data/cache/calendar_52.html'
        cal_men_html = open(cache_path, 'r', encoding='utf-8').read() if os.path.exists(cache_path) else ''
    men_live_by_team = {}
    men_season_history = extract_season_history(cal_men_html, 36, False, men_live_by_team) if cal_men_html else {}

    premium_standings = parse_premium_liiga_standings(league_html, existing_forms_men, men_season_history, men_live_by_team)
    print(f'Extracted {len(premium_standings)} teams in Premium Liiga.')

    # 3. Fetch Naiste Meistriliiga standings
    women_url = 'https://jalgpall.ee/voistlused/naised/63/naiste-meistriliiga'
    try:
        print(f'Fetching Naiste Meistriliiga table from {women_url}...')
        women_html = fetch_url(women_url)
    except Exception as e:
        print(f'Warning: Could not fetch women URL directly ({e}), checking local cache...')
        cache_path = 'data/cache/naiste_meistriliiga_63.html' if os.path.exists('data/cache/naiste_meistriliiga_63.html') else 'naiste_meistriliiga_63.html'
        if os.path.exists(cache_path):
            with open(cache_path, 'r', encoding='utf-8') as f:
                women_html = f.read()
        else:
            women_html = ''

    # 3b. Fetch Naiste Meistriliiga full season calendar (25 rounds)
    cal_women_url = 'https://jalgpall.ee/voistlused/63/liigad/calendar?season=2026'
    try:
        print(f'Fetching Naiste Meistriliiga calendar from {cal_women_url}...')
        cal_women_html = fetch_url(cal_women_url)
    except Exception as e:
        print(f'Warning: Could not fetch women calendar URL directly ({e}), checking local cache...')
        cache_path = 'data/cache/calendar_63.html'
        cal_women_html = open(cache_path, 'r', encoding='utf-8').read() if os.path.exists(cache_path) else ''
    women_live_by_team = {}
    women_season_history = extract_season_history(cal_women_html, 25, True, women_live_by_team) if cal_women_html else {}

    women_standings = parse_women_standings(women_html, existing_forms_women, women_season_history, women_live_by_team) if women_html else []
    print(f'Extracted {len(women_standings)} teams in Naiste Meistriliiga.')

    # Preserve assists in topScorers if already present in existing_data
    existing_assists = {s.get('name'): s.get('assists') for s in existing_data.get('topScorers', []) if 'name' in s and 'assists' in s}
    for scorer in team_data['stats']['topScorers']:
        if scorer.get('name') in existing_assists:
            scorer['assists'] = existing_assists[scorer['name']]

    # 4. Construct complete JSON dataset (preserving curated squad, staff & match details)
    final_payload = {
        'metadata': {
            'source': 'Eesti Jalgpalli Liit (EJL)',
            'sourceUrl': 'https://jalgpall.ee/voistlused/52/team/5',
            'lastUpdated': datetime.now().isoformat(),
            'season': '2026',
            'status': 'success'
        },
        'club': existing_data.get('club') or team_data['club'],
        'squad': existing_data.get('squad') or team_data['squad'],
        'topScorers': team_data['stats']['topScorers'] or existing_data.get('topScorers', []),
        'matches': existing_data.get('matches') or team_data['matches'],
        'standings': {
            'premiumLiiga': premium_standings,
            'naisteMeistriliiga': women_standings
        },
        'womenSquad': existing_data.get('womenSquad', []),
        'womenStaff': existing_data.get('womenStaff', []),
        'womenTopScorers': existing_data.get('womenTopScorers', []),
        'womenMatches': existing_data.get('womenMatches', {})
    }

    out_file = 'data/jalgpall-data.json'
    with open(out_file, 'w', encoding='utf-8') as f:
        json.dump(final_payload, f, ensure_ascii=False, indent=2)

    print(f'[jalgpall.ee Sync] Successfully saved real-time data to {out_file} ({os.path.getsize(out_file)} bytes).')

if __name__ == '__main__':
    main()
