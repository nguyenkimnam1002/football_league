// Sample data - 32 players with 5 simplified positions
const players = [
    { "ten": "Nguyễn Văn Nam", "vi_tri_chinh": "Hậu vệ cánh", "vi_tri_phu": "Tiền vệ", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Nguyễn Văn Tuấn", "vi_tri_chinh": "Trung vệ", "vi_tri_phu": "Hậu vệ cánh", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Đào Văn Đăng", "vi_tri_chinh": "Tiền đạo", "vi_tri_phu": "Hậu vệ cánh", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Yếu" },
    { "ten": "Đàm Minh Thư", "vi_tri_chinh": "Hậu vệ cánh", "vi_tri_phu": "Tiền đạo", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Trung bình" },
    { "ten": "Hà Văn Nam", "vi_tri_chinh": "Trung vệ", "vi_tri_phu": "Hậu vệ cánh", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Đỗ Minh Hoàng", "vi_tri_chinh": "Hậu vệ cánh", "vi_tri_phu": "Thủ môn", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Yếu" },
    { "ten": "Nguyễn Anh Việt", "vi_tri_chinh": "Tiền đạo", "vi_tri_phu": "Hậu vệ cánh", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Yếu" },
    { "ten": "Lê Hoàng Minh", "vi_tri_chinh": "Trung vệ", "vi_tri_phu": "Hậu vệ cánh", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Trung bình" },
    { "ten": "Nguyễn Xuân Trường", "vi_tri_chinh": "Hậu vệ cánh", "vi_tri_phu": "Tiền đạo", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Trung bình" },
    { "ten": "Chu Văn Trường", "vi_tri_chinh": "Tiền vệ", "vi_tri_phu": "Hậu vệ cánh", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Trần Anh", "vi_tri_chinh": "Hậu vệ cánh", "vi_tri_phu": "Tiền đạo", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Trung bình" },
    { "ten": "Ngô Quyền", "vi_tri_chinh": "Thủ môn", "vi_tri_phu": "Tiền vệ", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Trung bình" },
    { "ten": "Trần Quyền", "vi_tri_chinh": "Tiền vệ", "vi_tri_phu": "Trung vệ", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Văn Đăng Tuấn", "vi_tri_chinh": "Tiền vệ", "vi_tri_phu": "Hậu vệ cánh", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Nguyễn Văn Linh", "vi_tri_chinh": "Tiền vệ", "vi_tri_phu": "Hậu vệ cánh", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Trần Minh Tú", "vi_tri_chinh": "Hậu vệ cánh", "vi_tri_phu": "Thủ môn", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Yếu" },
    { "ten": "Đỗ Văn Tính (C)", "vi_tri_chinh": "Tiền đạo", "vi_tri_phu": "Tiền vệ", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Hoàng Văn Hồng", "vi_tri_chinh": "Hậu vệ cánh", "vi_tri_phu": "Tiền đạo", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Trung bình" },
    { "ten": "Hoàng Văn Chung", "vi_tri_chinh": "Trung vệ", "vi_tri_phu": "Hậu vệ cánh", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Nguyễn Quang Tuấn", "vi_tri_chinh": "Thủ môn", "vi_tri_phu": "Hậu vệ cánh", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Nguyễn Văn Quân", "vi_tri_chinh": "Tiền đạo", "vi_tri_phu": "Thủ môn", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Trần Văn Khoa", "vi_tri_chinh": "Tiền vệ", "vi_tri_phu": "Hậu vệ cánh", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Nguyễn Kim Nam", "vi_tri_chinh": "Tiền vệ", "vi_tri_phu": "Tiền đạo", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Phạm Đình Đạt", "vi_tri_chinh": "Hậu vệ cánh", "vi_tri_phu": "Tiền đạo", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Trung bình" },
    { "ten": "Nguyễn Quang Linh Su", "vi_tri_chinh": "Hậu vệ cánh", "vi_tri_phu": "Tiền đạo", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Nguyễn Văn Trọng", "vi_tri_chinh": "Hậu vệ cánh", "vi_tri_phu": "Thủ môn", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Trung bình" },
    { "ten": "Đỗ Văn Hà", "vi_tri_chinh": "Hậu vệ cánh", "vi_tri_phu": "Tiền đạo", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Yếu" },
    { "ten": "Nguyễn Chí Đạt Lốp", "vi_tri_chinh": "Tiền vệ", "vi_tri_phu": "Trung vệ", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Trần Hải Nam", "vi_tri_chinh": "Tiền đạo", "vi_tri_phu": "Thủ môn", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Trung bình" },
    { "ten": "Đỗ Việt Anh", "vi_tri_chinh": "Hậu vệ cánh", "vi_tri_phu": "Tiền vệ", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Trần Văn Tuấn", "vi_tri_chinh": "Tiền đạo", "vi_tri_phu": "Tiền vệ", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Tốt" },
    { "ten": "Lê Hùng Quảng Cáo", "vi_tri_chinh": "Thủ môn", "vi_tri_phu": "Tiền đạo", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" }
];

let selectedPlayers = [];

// Initialize app
document.addEventListener('DOMContentLoaded', function() {
    renderPlayerGrid();
    updateStatus();
});

// Helper function to map old position names to new ones
function mapPosition(position) {
    return position === 'Hậu vệ giữa' ? 'Trung vệ' : position;
}

function renderPlayerGrid() {
    const grid = document.getElementById('playerGrid');
    grid.innerHTML = '';
    
    // 5 positions with Trung vệ instead of Hậu vệ giữa
    const positions = ['Thủ môn', 'Trung vệ', 'Hậu vệ cánh', 'Tiền vệ', 'Tiền đạo'];
    
    positions.forEach(position => {
        // Filter players for this position (with mapping)
        const playersInPosition = players.filter(player => {
            const mappedMainPos = mapPosition(player.vi_tri_chinh);
            return mappedMainPos === position;
        });
        
        if (playersInPosition.length > 0) {
            // Create position header
            const positionHeader = document.createElement('div');
            positionHeader.className = 'position-header';
            positionHeader.textContent = `⚽ ${position}`;
            grid.appendChild(positionHeader);
            
            // Create container for this position
            const positionContainer = document.createElement('div');
            positionContainer.className = 'position-container';
            
            // Add players
            playersInPosition.forEach(player => {
                const playerDiv = document.createElement('div');
                playerDiv.className = 'player-item-compact';
                playerDiv.onclick = () => togglePlayer(player.ten);
                
                const levelClass = player.trinh_do_chinh.toLowerCase().replace(' ', '-');
                const secondaryLevelClass = player.trinh_do_phu.toLowerCase().replace(' ', '-');
                
                // Map secondary position for display
                const displaySecondaryPos = mapPosition(player.vi_tri_phu);
                
                playerDiv.innerHTML = `
                    <div class="player-name-compact">${player.ten}</div>
                    <div class="player-levels">
                        <div class="player-level-compact level-${levelClass}">${player.trinh_do_chinh}</div>
                        <div class="player-secondary-info">
                            <span class="secondary-position">${displaySecondaryPos}</span>
                            <span class="player-level-secondary level-${secondaryLevelClass}">${player.trinh_do_phu}</span>
                        </div>
                    </div>
                `;
                
                if (selectedPlayers.includes(player.ten)) {
                    playerDiv.classList.add('selected');
                }
                
                positionContainer.appendChild(playerDiv);
            });
            
            grid.appendChild(positionContainer);
        }
    });
}

function togglePlayer(playerName) {
    const index = selectedPlayers.indexOf(playerName);
    
    if (index > -1) {
        selectedPlayers.splice(index, 1);
    } else {
        selectedPlayers.push(playerName);
    }
    
    renderPlayerGrid();
    updateStatus();
}

function updateStatus() {
    const count = selectedPlayers.length;
    const statusBar = document.getElementById('statusBar');
    const divideBtn = document.getElementById('divideBtn');
    const previewBtn = document.getElementById('previewBtn');
    
    if (count >= 14) {
        statusBar.innerHTML = `🎉 Đã chọn ${count} người - Có thể chia đội 7v7 ngay!`;
        statusBar.className = 'status-bar ready';
        divideBtn.disabled = false;
        previewBtn.disabled = false;
    } else {
        statusBar.innerHTML = `📊 Đã chọn: ${count} người - Cần ≥14 để chia đội 7v7`;
        statusBar.className = 'status-bar';
        divideBtn.disabled = true;
        previewBtn.disabled = true;
    }
}

function selectAll() {
    selectedPlayers = players.map(p => p.ten);
    renderPlayerGrid();
    updateStatus();
}

function clearAll() {
    selectedPlayers = [];
    renderPlayerGrid();
    updateStatus();
}

function selectTopRated() {
    const sorted = [...players].sort((a, b) => {
        const scoreA = a.trinh_do_chinh === 'Tốt' ? 3 : a.trinh_do_chinh === 'Trung bình' ? 2 : 1;
        const scoreB = b.trinh_do_chinh === 'Tốt' ? 3 : b.trinh_do_chinh === 'Trung bình' ? 2 : 1;
        return scoreB - scoreA;
    });
    
    selectedPlayers = sorted.slice(0, 14).map(p => p.ten);
    renderPlayerGrid();
    updateStatus();
}

function resetAll() {
    selectedPlayers = [];
    document.getElementById('teamsResult').innerHTML = '';
    renderPlayerGrid();
    updateStatus();
}

function divideTeams() {
    if (selectedPlayers.length < 14) {
        alert('Cần ít nhất 14 cầu thủ để chia đội 7v7!');
        return;
    }
    
    const selectedPlayerObjects = selectedPlayers
        .map(name => players.find(p => p.ten === name))
        .filter(p => p);
    
    const { teamA, teamB } = performTeamDivision(selectedPlayerObjects);
    displayTeams(teamA, teamB, false);
}

function previewTeams() {
    if (selectedPlayers.length < 14) {
        alert('Cần ít nhất 14 cầu thủ để xem trước đội 7v7!');
        return;
    }
    
    const selectedPlayerObjects = selectedPlayers
        .map(name => players.find(p => p.ten === name))
        .filter(p => p);
    
    const { teamA, teamB } = performTeamDivision(selectedPlayerObjects);
    displayTeams(teamA, teamB, true);
}

function performTeamDivision(playerList) {
    const positions = ['Thủ môn', 'Trung vệ', 'Hậu vệ cánh', 'Tiền vệ', 'Tiền đạo'];

    // Initialize teams
    const teamA = {};
    const teamB = {};
    positions.forEach(pos => {
        teamA[pos] = [];
        teamB[pos] = [];
    });

    const usedPlayers = new Set();
    const allPlayers = [...playerList];

    // STEP 1: Distribute players by their MAIN POSITION first (prioritize specialists)
    positions.forEach(position => {
        // Get all players who have this as their main position
        const playersInPosition = allPlayers
            .filter(player => {
                const mainPos = mapPosition(player.vi_tri_chinh);
                return mainPos === position && !usedPlayers.has(player.ten);
            })
            .sort((a, b) => {
                // Sort by skill level (Tốt > Trung bình > Yếu)
                const skillA = a.trinh_do_chinh === 'Tốt' ? 3 : a.trinh_do_chinh === 'Trung bình' ? 2 : 1;
                const skillB = b.trinh_do_chinh === 'Tốt' ? 3 : b.trinh_do_chinh === 'Trung bình' ? 2 : 1;
                return skillB - skillA;
            });

        // Special handling for goalkeepers - only 1 per team
        if (position === 'Thủ môn') {
            if (playersInPosition.length >= 1) {
                teamA[position].push({...playersInPosition[0], assignedPosition: position});
                usedPlayers.add(playersInPosition[0].ten);
            }
            if (playersInPosition.length >= 2) {
                teamB[position].push({...playersInPosition[1], assignedPosition: position});
                usedPlayers.add(playersInPosition[1].ten);
            }
            // Remaining goalkeepers will be handled later
            for (let i = 2; i < playersInPosition.length; i++) {
                // Keep them for later assignment to secondary positions
            }
        }
        // Special handling for strikers - distribute ALL good strikers
        else if (position === 'Tiền đạo') {
            // Distribute all strikers with good main position skill
            const goodStrikers = playersInPosition.filter(p => p.trinh_do_chinh === 'Tốt');
            const okayStrikers = playersInPosition.filter(p => p.trinh_do_chinh === 'Trung bình');
            
            // Distribute good strikers alternately
            goodStrikers.forEach((player, index) => {
                const teamASize = teamA[position].length;
                const teamBSize = teamB[position].length;
                
                if (teamASize <= teamBSize) {
                    teamA[position].push({...player, assignedPosition: position});
                } else {
                    teamB[position].push({...player, assignedPosition: position});
                }
                usedPlayers.add(player.ten);
            });
            
            // Add okay strikers if teams need more
            okayStrikers.forEach((player, index) => {
                const teamASize = teamA[position].length;
                const teamBSize = teamB[position].length;
                
                // Only add if no team has more than 1 good striker already
                if (teamASize === 0 || teamBSize === 0 || (teamASize <= teamBSize && teamASize < 2)) {
                    if (teamASize <= teamBSize) {
                        teamA[position].push({...player, assignedPosition: position});
                    } else {
                        teamB[position].push({...player, assignedPosition: position});
                    }
                    usedPlayers.add(player.ten);
                }
            });
        }
        // For other positions, distribute all good players to their main positions
        else {
            playersInPosition.forEach((player, index) => {
                const teamASize = Object.values(teamA).flat().length;
                const teamBSize = Object.values(teamB).flat().length;
                
                // Alternate assignment to maintain balance
                if (teamASize <= teamBSize) {
                    teamA[position].push({...player, assignedPosition: position});
                } else {
                    teamB[position].push({...player, assignedPosition: position});
                }
                usedPlayers.add(player.ten);
            });
        }
    });

    // STEP 2: Handle remaining goalkeepers (assign to secondary positions)
    const remainingGoalkeepers = allPlayers.filter(player => {
        const mainPos = mapPosition(player.vi_tri_chinh);
        return mainPos === 'Thủ môn' && !usedPlayers.has(player.ten);
    });

    remainingGoalkeepers.forEach(player => {
        const secondaryPos = mapPosition(player.vi_tri_phu) || 'Hậu vệ cánh';
        const teamASize = Object.values(teamA).flat().length;
        const teamBSize = Object.values(teamB).flat().length;
        
        if (teamASize <= teamBSize) {
            teamA[secondaryPos].push({...player, assignedPosition: secondaryPos});
        } else {
            teamB[secondaryPos].push({...player, assignedPosition: secondaryPos});
        }
        usedPlayers.add(player.ten);
    });

    // STEP 3: Handle remaining strikers (assign to secondary positions)
    const remainingStrikers = allPlayers.filter(player => {
        const mainPos = mapPosition(player.vi_tri_chinh);
        return mainPos === 'Tiền đạo' && !usedPlayers.has(player.ten);
    });

    remainingStrikers.forEach(player => {
        const secondaryPos = mapPosition(player.vi_tri_phu) || 'Tiền vệ';
        const teamASize = Object.values(teamA).flat().length;
        const teamBSize = Object.values(teamB).flat().length;
        
        if (teamASize <= teamBSize) {
            teamA[secondaryPos].push({...player, assignedPosition: secondaryPos});
        } else {
            teamB[secondaryPos].push({...player, assignedPosition: secondaryPos});
        }
        usedPlayers.add(player.ten);
    });

    // STEP 4: Handle any remaining unassigned players
    const finalRemaining = allPlayers.filter(player => !usedPlayers.has(player.ten));
    
    finalRemaining.forEach(player => {
        // Try secondary position first, then main position
        const secondaryPos = mapPosition(player.vi_tri_phu);
        const mainPos = mapPosition(player.vi_tri_chinh);
        const assignPosition = secondaryPos || mainPos;
        
        const teamASize = Object.values(teamA).flat().length;
        const teamBSize = Object.values(teamB).flat().length;
        
        if (teamASize <= teamBSize) {
            teamA[assignPosition].push({...player, assignedPosition: assignPosition});
        } else {
            teamB[assignPosition].push({...player, assignedPosition: assignPosition});
        }
        usedPlayers.add(player.ten);
    });

    // FINAL VALIDATION
    const totalA = Object.values(teamA).flat().length;
    const totalB = Object.values(teamB).flat().length;
    const gkA = teamA['Thủ môn'].length;
    const gkB = teamB['Thủ môn'].length;
    const strikerA = teamA['Tiền đạo'].length;
    const strikerB = teamB['Tiền đạo'].length;
    
    console.log('=== FINAL TEAM COMPOSITION ===');
    console.log(`Team A: ${totalA} players (GK: ${gkA}, Strikers: ${strikerA})`);
    console.log(`Team B: ${totalB} players (GK: ${gkB}, Strikers: ${strikerB})`);
    console.log(`Total assigned: ${totalA + totalB}/${allPlayers.length}`);

    return { teamA, teamB };
}

function generateTeamHTML(team) {
    const positions = ['Thủ môn', 'Trung vệ', 'Hậu vệ cánh', 'Tiền vệ', 'Tiền đạo'];
    
    // Split positions into two columns
    const leftColumn = ['Thủ môn', 'Trung vệ', 'Tiền vệ'];
    const rightColumn = ['Hậu vệ cánh', 'Tiền đạo'];
    
    let html = '<div class="team-positions">';
    
    // Left column
    html += '<div class="team-column-left">';
    leftColumn.forEach(position => {
        if (team[position] && team[position].length > 0) {
            html += `<div class="position-group">`;
            html += `<div class="position-title">${position}</div>`;
            
            team[position].forEach(player => {
                let level = '';
                let positionType = '';
                
                const assignedPos = player.assignedPosition || position;
                const playerMainPos = mapPosition(player.vi_tri_chinh);
                const playerSecPos = mapPosition(player.vi_tri_phu);
                
                if (playerMainPos === assignedPos) {
                    level = player.trinh_do_chinh;
                    positionType = 'Sở trường';
                } else if (playerSecPos === assignedPos) {
                    level = player.trinh_do_phu;
                    positionType = 'Sở đoản';
                } else {
                    level = 'Yếu';
                    positionType = 'Không quen';
                }
                
                const levelClass = level.toLowerCase().replace(' ', '-');
                
                html += `
                    <div class="team-player-detailed ${levelClass}">
                        <div class="player-name-team">${player.ten}</div>
                        <div class="player-info">
                            <span class="position-type">${positionType}</span>
                            <span class="level-badge">${level}</span>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
        }
    });
    html += '</div>';
    
    // Right column
    html += '<div class="team-column-right">';
    rightColumn.forEach(position => {
        if (team[position] && team[position].length > 0) {
            html += `<div class="position-group">`;
            html += `<div class="position-title">${position}</div>`;
            
            team[position].forEach(player => {
                let level = '';
                let positionType = '';
                
                const assignedPos = player.assignedPosition || position;
                const playerMainPos = mapPosition(player.vi_tri_chinh);
                const playerSecPos = mapPosition(player.vi_tri_phu);
                
                if (playerMainPos === assignedPos) {
                    level = player.trinh_do_chinh;
                    positionType = 'Sở trường';
                } else if (playerSecPos === assignedPos) {
                    level = player.trinh_do_phu;
                    positionType = 'Sở đoản';
                } else {
                    level = 'Yếu';
                    positionType = 'Không quen';
                }
                
                const levelClass = level.toLowerCase().replace(' ', '-');
                
                html += `
                    <div class="team-player-detailed ${levelClass}">
                        <div class="player-name-team">${player.ten}</div>
                        <div class="player-info">
                            <span class="position-type">${positionType}</span>
                            <span class="level-badge">${level}</span>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
        }
    });
    html += '</div>';
    
    html += '</div>';
    
    return html;
}

function displayTeams(teamA, teamB, isPreview) {
    const teamsResult = document.getElementById('teamsResult');
    
    const countA = Object.values(teamA).flat().length;
    const countB = Object.values(teamB).flat().length;
    
    let html = `
        <div class="stats">
            <h3>${isPreview ? '👀 Xem Trước Đội Hình' : '⚽ Kết Quả Chia Đội'}</h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number">${countA}</div>
                    <div class="stat-label">Đội A</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">${countB}</div>
                    <div class="stat-label">Đội B</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">${selectedPlayers.length}</div>
                    <div class="stat-label">Tổng cầu thủ</div>
                </div>
            </div>
        </div>
    `;
    
    html += '<div class="teams-container">';
    
    // Team A
    html += '<div class="team team-a">';
    html += '<h3 class="team-title">🔴 ĐỘI A</h3>';
    html += generateTeamHTML(teamA);
    html += '</div>';
    
    // Team B  
    html += '<div class="team team-b">';
    html += '<h3 class="team-title">🔵 ĐỘI B</h3>';
    html += generateTeamHTML(teamB);
    html += '</div>';
    
    html += '</div>';
    
    if (isPreview) {
        html += `
            <div style="text-align: center; margin-top: 20px;">
                <button class="btn btn-divide" onclick="divideTeams()">✅ Xác Nhận Chia Đội</button>
            </div>
        `;
    }
    
    teamsResult.innerHTML = html;
    teamsResult.scrollIntoView({ behavior: 'smooth' });
}