// Sample data - 30 players with 5 simplified positions
const players = [
    { "ten": "Nguyễn Văn An", "vi_tri_chinh": "Thủ môn", "vi_tri_phu": "Hậu vệ giữa", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Yếu" },
    { "ten": "Trần Minh Bảo", "vi_tri_chinh": "Hậu vệ cánh", "vi_tri_phu": "Tiền vệ", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Yếu" },
    { "ten": "Lê Hoàng Cường", "vi_tri_chinh": "Hậu vệ cánh", "vi_tri_phu": "Tiền vệ", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Phạm Văn Dũng", "vi_tri_chinh": "Hậu vệ giữa", "vi_tri_phu": "Hậu vệ cánh", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Vũ Thanh Em", "vi_tri_chinh": "Tiền vệ", "vi_tri_phu": "Hậu vệ giữa", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Yếu" },
    { "ten": "Đỗ Quang Phú", "vi_tri_chinh": "Tiền vệ", "vi_tri_phu": "Tiền đạo", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Hoàng Minh Giang", "vi_tri_chinh": "Tiền vệ", "vi_tri_phu": "Tiền đạo", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Yếu" },
    { "ten": "Ngô Văn Hùng", "vi_tri_chinh": "Tiền vệ", "vi_tri_phu": "Tiền đạo", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Bùi Đức Ích", "vi_tri_chinh": "Tiền đạo", "vi_tri_phu": "Tiền vệ", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Cao Văn Khang", "vi_tri_chinh": "Thủ môn", "vi_tri_phu": "Hậu vệ giữa", "trinh_do_chinh": "Yếu", "trinh_do_phu": "Yếu" },
    { "ten": "Đinh Hoài Long", "vi_tri_chinh": "Hậu vệ cánh", "vi_tri_phu": "Tiền vệ", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Yếu" },
    { "ten": "Lý Văn Mạnh", "vi_tri_chinh": "Hậu vệ cánh", "vi_tri_phu": "Tiền vệ", "trinh_do_chinh": "Yếu", "trinh_do_phu": "Yếu" },
    { "ten": "Mai Quốc Nam", "vi_tri_chinh": "Hậu vệ giữa", "vi_tri_phu": "Hậu vệ cánh", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Yếu" },
    { "ten": "Phan Văn Oanh", "vi_tri_chinh": "Tiền vệ", "vi_tri_phu": "Thủ môn", "trinh_do_chinh": "Yếu", "trinh_do_phu": "Yếu" },
    { "ten": "Trịnh Minh Phát", "vi_tri_chinh": "Tiền vệ", "vi_tri_phu": "Tiền đạo", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Yếu" },
    { "ten": "Vương Đức Quang", "vi_tri_chinh": "Tiền vệ", "vi_tri_phu": "Tiền đạo", "trinh_do_chinh": "Yếu", "trinh_do_phu": "Yếu" },
    { "ten": "Đặng Văn Rùa", "vi_tri_chinh": "Tiền vệ", "vi_tri_phu": "Hậu vệ cánh", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Trung bình" },
    { "ten": "Hồ Minh Sơn", "vi_tri_chinh": "Tiền đạo", "vi_tri_phu": "Tiền vệ", "trinh_do_chinh": "Yếu", "trinh_do_phu": "Yếu" },
    { "ten": "Lê Văn Tú", "vi_tri_chinh": "Thủ môn", "vi_tri_phu": "Hậu vệ giữa", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Nguyễn Hoàng Uy", "vi_tri_chinh": "Hậu vệ cánh", "vi_tri_phu": "Hậu vệ giữa", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Tốt" },
    { "ten": "Võ Thanh Vân", "vi_tri_chinh": "Hậu vệ cánh", "vi_tri_phu": "Tiền vệ", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Trung bình" },
    { "ten": "Đoàn Minh Xuân", "vi_tri_chinh": "Hậu vệ giữa", "vi_tri_phu": "Tiền vệ", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Yếu" },
    { "ten": "Chu Văn Yên", "vi_tri_chinh": "Tiền vệ", "vi_tri_phu": "Hậu vệ cánh", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Hoàng Đức Zũng", "vi_tri_chinh": "Tiền vệ", "vi_tri_phu": "Tiền đạo", "trinh_do_chinh": "Yếu", "trinh_do_phu": "Yếu" },
    { "ten": "Trần Văn Anh", "vi_tri_chinh": "Hậu vệ cánh", "vi_tri_phu": "Tiền vệ", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Nguyễn Thanh Bình", "vi_tri_chinh": "Tiền vệ", "vi_tri_phu": "Tiền đạo", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Trung bình" },
    { "ten": "Lê Minh Chiến", "vi_tri_chinh": "Tiền đạo", "vi_tri_phu": "Tiền vệ", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Yếu" },
    { "ten": "Phạm Quang Đạt", "vi_tri_chinh": "Thủ môn", "vi_tri_phu": "Hậu vệ giữa", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Yếu" },
    { "ten": "Vũ Hoàng Hải", "vi_tri_chinh": "Hậu vệ giữa", "vi_tri_phu": "Hậu vệ cánh", "trinh_do_chinh": "Yếu", "trinh_do_phu": "Yếu" },
    { "ten": "Đỗ Văn Kiên", "vi_tri_chinh": "Tiền đạo", "vi_tri_phu": "Tiền vệ", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Tốt" }
];

let selectedPlayers = [];

// Initialize app
document.addEventListener('DOMContentLoaded', function() {
    renderPlayerGrid();
    updateStatus();
});

function renderPlayerGrid() {
    const grid = document.getElementById('playerGrid');
    grid.innerHTML = '';
    
    // 5 simplified positions only
    const positions = ['Thủ môn', 'Hậu vệ giữa', 'Hậu vệ cánh', 'Tiền vệ', 'Tiền đạo'];
    
    positions.forEach(position => {
        const playersInPosition = players.filter(player => player.vi_tri_chinh === position);
        
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
                
                playerDiv.innerHTML = `
                    <div class="player-name-compact">${player.ten}</div>
                    <div class="player-level-compact level-${levelClass}">${player.trinh_do_chinh}</div>
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
    // 5 simplified positions for 7-a-side
    const positions = ['Thủ môn', 'Hậu vệ giữa', 'Hậu vệ cánh', 'Tiền vệ', 'Tiền đạo'];

    // Initialize teams with assigned positions structure
    const teamA = {};
    const teamB = {};
    positions.forEach(pos => {
        teamA[pos] = [];
        teamB[pos] = [];
    });

    // Function to calculate player score for a position
    function getPlayerScore(player, position) {
        let score = 0;
        if (player.vi_tri_chinh === position) {
            score = player.trinh_do_chinh === 'Tốt' ? 3 : 
                   player.trinh_do_chinh === 'Trung bình' ? 2 : 1;
        } else if (player.vi_tri_phu === position) {
            score = player.trinh_do_phu === 'Tốt' ? 2.5 : 
                   player.trinh_do_phu === 'Trung bình' ? 1.5 : 0.5;
        }
        return score;
    }

    // Function to get team's total player count
    function getTeamPlayerCount(team) {
        return positions.reduce((count, pos) => count + team[pos].length, 0);
    }

    // Function to get team line counts (defense, midfield, attack)
    function getTeamLineBalance(team) {
        const defense = team['Hậu vệ giữa'].length + team['Hậu vệ cánh'].length;
        const midfield = team['Tiền vệ'].length;
        const attack = team['Tiền đạo'].length;
        return { defense, midfield, attack };
    }

    // Create enriched player objects with ALL possible positions and scores
    const enrichedPlayers = playerList.map(player => {
        const positionScores = {};
        positions.forEach(pos => {
            positionScores[pos] = getPlayerScore(player, pos);
        });
        
        return {
            ...player,
            positionScores,
            assignedPosition: null // Will be determined later
        };
    });

    const usedPlayers = new Set();

    // Step 1: First pass - assign players to their strongest positions
    const positionPriority = ['Thủ môn', 'Hậu vệ giữa', 'Hậu vệ cánh', 'Tiền vệ', 'Tiền đạo'];
    
    // Group players by their best positions (primary and secondary)
    const playersByBestPosition = {};
    positions.forEach(position => {
        playersByBestPosition[position] = enrichedPlayers
            .filter(player => 
                player.vi_tri_chinh === position || 
                (player.vi_tri_phu === position && getPlayerScore(player, position) > 0)
            )
            .sort((a, b) => b.positionScores[position] - a.positionScores[position]);
    });

    // First distribute key positions (goalkeeper and critical positions)
    positionPriority.forEach(position => {
        const availablePlayers = playersByBestPosition[position].filter(player => 
            !usedPlayers.has(player.ten)
        );

        availablePlayers.forEach((player, index) => {
            const teamACount = getTeamPlayerCount(teamA);
            const teamBCount = getTeamPlayerCount(teamB);
            const teamAPositionCount = teamA[position].length;
            const teamBPositionCount = teamB[position].length;

            // For goalkeeper, ensure each team gets one
            if (position === 'Thủ môn') {
                if (teamAPositionCount === 0) {
                    teamA[position].push({...player, assignedPosition: position});
                    usedPlayers.add(player.ten);
                    return;
                } else if (teamBPositionCount === 0) {
                    teamB[position].push({...player, assignedPosition: position});
                    usedPlayers.add(player.ten);
                    return;
                }
            }

            // For other positions, balance between teams
            let assignToTeamA;
            
            if (teamAPositionCount !== teamBPositionCount) {
                assignToTeamA = teamAPositionCount < teamBPositionCount;
            } else if (teamACount !== teamBCount) {
                assignToTeamA = teamACount < teamBCount;
            } else {
                assignToTeamA = index % 2 === 0;
            }

            if (assignToTeamA) {
                teamA[position].push({...player, assignedPosition: position});
            } else {
                teamB[position].push({...player, assignedPosition: position});
            }
            
            usedPlayers.add(player.ten);
        });
    });

    // Step 2: Handle remaining players with flexible positioning
    const remainingPlayers = enrichedPlayers.filter(player => !usedPlayers.has(player.ten));
    
    remainingPlayers.forEach(player => {
        // Find the best position for this player considering team balance
        const teamABalance = getTeamLineBalance(teamA);
        const teamBBalance = getTeamLineBalance(teamB);
        
        // Determine which team needs more players
        const teamACount = getTeamPlayerCount(teamA);
        const teamBCount = getTeamPlayerCount(teamB);
        const preferTeamA = teamACount <= teamBCount;
        
        // Find best position for this player considering team needs
        let bestPosition = player.vi_tri_chinh;
        let bestScore = player.positionScores[bestPosition];
        
        // Check if secondary position is viable and needed
        if (player.positionScores[player.vi_tri_phu] > 0) {
            const targetTeam = preferTeamA ? teamA : teamB;
            const targetBalance = preferTeamA ? teamABalance : teamBBalance;
            
            // Check if the target team needs more players in secondary position's line
            const primaryLine = getPositionLine(bestPosition);
            const secondaryLine = getPositionLine(player.vi_tri_phu);
            
            if (secondaryLine !== primaryLine) {
                const primaryLineCount = getLineCount(targetBalance, primaryLine);
                const secondaryLineCount = getLineCount(targetBalance, secondaryLine);
                
                // If secondary line needs more players, consider switching
                if (secondaryLineCount < primaryLineCount && player.positionScores[player.vi_tri_phu] >= 1.5) {
                    bestPosition = player.vi_tri_phu;
                    bestScore = player.positionScores[bestPosition];
                }
            }
        }
        
        // Assign to the chosen team
        if (preferTeamA) {
            teamA[bestPosition].push({...player, assignedPosition: bestPosition});
        } else {
            teamB[bestPosition].push({...player, assignedPosition: bestPosition});
        }
        
        usedPlayers.add(player.ten);
    });

    // Helper functions for line balance
    function getPositionLine(position) {
        if (position === 'Hậu vệ giữa' || position === 'Hậu vệ cánh') return 'defense';
        if (position === 'Tiền vệ') return 'midfield';
        if (position === 'Tiền đạo') return 'attack';
        return 'special'; // goalkeeper
    }
    
    function getLineCount(balance, line) {
        switch(line) {
            case 'defense': return balance.defense;
            case 'midfield': return balance.midfield;
            case 'attack': return balance.attack;
            default: return 0;
        }
    }

    // Step 3: Final balance adjustment
    const finalTeamACount = getTeamPlayerCount(teamA);
    const finalTeamBCount = getTeamPlayerCount(teamB);
    
    if (Math.abs(finalTeamACount - finalTeamBCount) > 1) {
        const largerTeam = finalTeamACount > finalTeamBCount ? teamA : teamB;
        const smallerTeam = finalTeamACount > finalTeamBCount ? teamB : teamA;
        
        // Find a player to move (prefer from positions with multiple players)
        for (const position of [...positions].reverse()) {
            if (largerTeam[position].length > 1) {
                const playerToMove = largerTeam[position].pop();
                smallerTeam[position].push(playerToMove);
                break;
            }
        }
    }

    return { teamA, teamB };
}

function generateTeamHTML(team) {
    let html = '';
    
    const positions = ['Thủ môn', 'Hậu vệ giữa', 'Hậu vệ cánh', 'Tiền vệ', 'Tiền đạo'];
    
    positions.forEach(position => {
        if (team[position] && team[position].length > 0) {
            html += `<div class="position-group">`;
            html += `<div class="position-title">${position}</div>`;
            
            team[position].forEach(player => {
                let level = '';
                let positionType = '';
                
                // Use assigned position for determining skill level
                const assignedPos = player.assignedPosition || position;
                
                if (player.vi_tri_chinh === assignedPos) {
                    level = player.trinh_do_chinh;
                    positionType = 'Sở trường';
                } else if (player.vi_tri_phu === assignedPos) {
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
                            <span class="position-type">${positionType} - ${level}</span>
                            <span class="level-badge">${level}</span>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
        }
    });
    
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