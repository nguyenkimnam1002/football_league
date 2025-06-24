// Sample data - 30 players with 5 simplified positions
const players = [
    { "ten": "Nguyễn Văn Nam", "vi_tri_chinh": "Hậu vệ cánh", "vi_tri_phu": "Tiền vệ", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Nguyễn Văn Tuấn", "vi_tri_chinh": "Hậu vệ giữa", "vi_tri_phu": "Hậu vệ cánh", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Đào Văn Đăng", "vi_tri_chinh": "Tiền đạo", "vi_tri_phu": "Hậu vệ cánh", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Yếu" },
    { "ten": "Đàm Minh Thư", "vi_tri_chinh": "Hậu vệ cánh", "vi_tri_phu": "Tiền đạo", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Trung bình" },
    { "ten": "Hà Văn Nam", "vi_tri_chinh": "Hậu vệ giữa", "vi_tri_phu": "Hậu vệ cánh", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Đỗ Minh Hoàng", "vi_tri_chinh": "Thủ môn", "vi_tri_phu": "Hậu vệ cánh", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Yếu" },
    { "ten": "Nguyễn Anh Việt", "vi_tri_chinh": "Tiền đạo", "vi_tri_phu": "Hậu vệ cánh", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Yếu" },
    { "ten": "Lê Hoàng Minh", "vi_tri_chinh": "Hậu vệ giữa", "vi_tri_phu": "Hậu vệ cánh", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Trung bình" },
    { "ten": "Nguyễn Xuân Trường", "vi_tri_chinh": "Hậu vệ cánh", "vi_tri_phu": "Tiền đạo", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Trung bình" },
    { "ten": "Chu Văn Trường", "vi_tri_chinh": "Tiền vệ", "vi_tri_phu": "Hậu vệ cánh", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Trần Anh", "vi_tri_chinh": "Hậu vệ cánh", "vi_tri_phu": "Tiền đạo", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Trung bình" },
    { "ten": "Ngô Quyền", "vi_tri_chinh": "Thủ môn", "vi_tri_phu": "Tiền vệ", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Trung bình" },
    { "ten": "Trần Quyền", "vi_tri_chinh": "Tiền vệ", "vi_tri_phu": "Hậu vệ giữa", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Văn Đăng Tuấn", "vi_tri_chinh": "Tiền vệ", "vi_tri_phu": "Hậu vệ cánh", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Nguyễn Văn Linh", "vi_tri_chinh": "Tiền vệ", "vi_tri_phu": "Hậu vệ cánh", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Trần Minh Tú", "vi_tri_chinh": "Hậu vệ cánh", "vi_tri_phu": "Thủ môn", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Yếu" },
    { "ten": "Đỗ Văn Tính (C)", "vi_tri_chinh": "Tiền đạo", "vi_tri_phu": "Tiền vệ", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Hoàng Văn Hồng", "vi_tri_chinh": "Hậu vệ cánh", "vi_tri_phu": "Tiền đạo", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Trung bình" },
    { "ten": "Hoàng Văn Chung", "vi_tri_chinh": "Hậu vệ giữa", "vi_tri_phu": "Hậu vệ cánh", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Nguyễn Quang Tuấn", "vi_tri_chinh": "Thủ môn", "vi_tri_phu": "Hậu vệ cánh", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Nguyễn Văn Quân", "vi_tri_chinh": "Tiền đạo", "vi_tri_phu": "Thủ môn", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Trung bình" },
    { "ten": "Trần Văn Khoa", "vi_tri_chinh": "Tiền vệ", "vi_tri_phu": "Hậu vệ cánh", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Nguyễn Kim Nam", "vi_tri_chinh": "Tiền vệ", "vi_tri_phu": "Tiền đạo", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Phạm Đình Đạt", "vi_tri_chinh": "Hậu vệ cánh", "vi_tri_phu": "Tiền đạo", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Trung bình" },
    { "ten": "Nguyễn Quang Linh Su", "vi_tri_chinh": "Hậu vệ cánh", "vi_tri_phu": "Tiền đạo", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
    { "ten": "Nguyễn Văn Trọng", "vi_tri_chinh": "Hậu vệ cánh", "vi_tri_phu": "Thủ môn", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Trung bình" },
    { "ten": "Đỗ Văn Hà", "vi_tri_chinh": "Hậu vệ cánh", "vi_tri_phu": "Tiền đạo", "trinh_do_chinh": "Trung bình", "trinh_do_phu": "Yếu" },
    { "ten": "Nguyễn Chí Đạt Lốp", "vi_tri_chinh": "Tiền vệ", "vi_tri_phu": "Hậu vệ giữa", "trinh_do_chinh": "Tốt", "trinh_do_phu": "Trung bình" },
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
                const secondaryLevelClass = player.trinh_do_phu.toLowerCase().replace(' ', '-');
                
                playerDiv.innerHTML = `
                    <div class="player-name-compact">${player.ten}</div>
                    <div class="player-levels">
                        <div class="player-level-compact level-${levelClass}">${player.trinh_do_chinh}</div>
                        <div class="player-secondary-info">
                            <span class="secondary-position">${player.vi_tri_phu}</span>
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

    const usedPlayers = new Set();

    // Step 1: Ensure GOALKEEPER for each team (EXACTLY ONE EACH)
    const goalkeepers = playerList
        .filter(player => 
            (player.vi_tri_chinh === 'Thủ môn' || player.vi_tri_phu === 'Thủ môn') &&
            !usedPlayers.has(player.ten)
        )
        .sort((a, b) => getPlayerScore(b, 'Thủ môn') - getPlayerScore(a, 'Thủ môn'));

    // Assign exactly one goalkeeper to each team
    let teamAHasGK = false;
    let teamBHasGK = false;
    
    goalkeepers.forEach((player) => {
        if (usedPlayers.has(player.ten)) return;
        
        if (!teamAHasGK) {
            teamA['Thủ môn'].push({...player, assignedPosition: 'Thủ môn'});
            usedPlayers.add(player.ten);
            teamAHasGK = true;
        } else if (!teamBHasGK) {
            teamB['Thủ môn'].push({...player, assignedPosition: 'Thủ môn'});
            usedPlayers.add(player.ten);
            teamBHasGK = true;
        }
        // Stop after both teams have goalkeepers
        if (teamAHasGK && teamBHasGK) return;
    });

    // Step 2: Ensure STRIKER for each team (EXACTLY ONE EACH)
    const strikers = playerList
        .filter(player => 
            (player.vi_tri_chinh === 'Tiền đạo' || player.vi_tri_phu === 'Tiền đạo') &&
            !usedPlayers.has(player.ten)
        )
        .sort((a, b) => getPlayerScore(b, 'Tiền đạo') - getPlayerScore(a, 'Tiền đạo'));

    // Assign exactly one striker to each team
    let teamAHasStriker = false;
    let teamBHasStriker = false;
    
    strikers.forEach((player) => {
        if (usedPlayers.has(player.ten)) return;
        
        if (!teamAHasStriker) {
            teamA['Tiền đạo'].push({...player, assignedPosition: 'Tiền đạo'});
            usedPlayers.add(player.ten);
            teamAHasStriker = true;
        } else if (!teamBHasStriker) {
            teamB['Tiền đạo'].push({...player, assignedPosition: 'Tiền đạo'});
            usedPlayers.add(player.ten);
            teamBHasStriker = true;
        }
        // Stop after both teams have strikers
        if (teamAHasStriker && teamBHasStriker) return;
    });

    // Step 3: Ensure each team has at least one good midfielder
    const goodMidfielders = playerList
        .filter(player => 
            player.vi_tri_chinh === 'Tiền vệ' && 
            player.trinh_do_chinh === 'Tốt' &&
            !usedPlayers.has(player.ten)
        )
        .sort((a, b) => getPlayerScore(b, 'Tiền vệ') - getPlayerScore(a, 'Tiền vệ'));

    goodMidfielders.forEach((player, index) => {
        if (usedPlayers.has(player.ten)) return;

        const teamAGoodMidfielders = teamA['Tiền vệ'].filter(p => 
            p.vi_tri_chinh === 'Tiền vệ' && p.trinh_do_chinh === 'Tốt'
        ).length;
        const teamBGoodMidfielders = teamB['Tiền vệ'].filter(p => 
            p.vi_tri_chinh === 'Tiền vệ' && p.trinh_do_chinh === 'Tốt'
        ).length;

        if (teamAGoodMidfielders === 0) {
            teamA['Tiền vệ'].push({...player, assignedPosition: 'Tiền vệ'});
            usedPlayers.add(player.ten);
        } else if (teamBGoodMidfielders === 0) {
            teamB['Tiền vệ'].push({...player, assignedPosition: 'Tiền vệ'});
            usedPlayers.add(player.ten);
        } else if (teamAGoodMidfielders <= teamBGoodMidfielders) {
            teamA['Tiền vệ'].push({...player, assignedPosition: 'Tiền vệ'});
            usedPlayers.add(player.ten);
        } else {
            teamB['Tiền vệ'].push({...player, assignedPosition: 'Tiền vệ'});
            usedPlayers.add(player.ten);
        }
    });

    // Step 4: Distribute remaining players to their primary positions first
    const remainingPositions = ['Hậu vệ giữa', 'Hậu vệ cánh', 'Tiền vệ'];
    
    remainingPositions.forEach(position => {
        const playersInPosition = playerList
            .filter(player => 
                player.vi_tri_chinh === position && 
                !usedPlayers.has(player.ten)
            )
            .sort((a, b) => getPlayerScore(b, position) - getPlayerScore(a, position));

        playersInPosition.forEach((player, index) => {
            const teamACount = getTeamPlayerCount(teamA);
            const teamBCount = getTeamPlayerCount(teamB);
            const teamAPositionCount = teamA[position].length;
            const teamBPositionCount = teamB[position].length;

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

    // Step 5: Handle any remaining players with flexible positioning
    const stillRemainingPlayers = playerList.filter(player => !usedPlayers.has(player.ten));
    
    stillRemainingPlayers.forEach(player => {
        // Determine which team needs more players
        const teamACount = getTeamPlayerCount(teamA);
        const teamBCount = getTeamPlayerCount(teamB);
        const preferTeamA = teamACount <= teamBCount;
        
        // Find the best position for this player
        let bestPosition = player.vi_tri_chinh;
        let bestScore = getPlayerScore(player, bestPosition);
        
        // Check if secondary position would be better for team balance
        if (player.vi_tri_phu && getPlayerScore(player, player.vi_tri_phu) >= 1.5) {
            const targetTeam = preferTeamA ? teamA : teamB;
            const primaryPosCount = targetTeam[player.vi_tri_chinh].length;
            const secondaryPosCount = targetTeam[player.vi_tri_phu] ? targetTeam[player.vi_tri_phu].length : 0;
            
            // Switch to secondary position if it has fewer players and is beneficial
            if (secondaryPosCount < primaryPosCount) {
                bestPosition = player.vi_tri_phu;
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

    // Step 6: Final validation and emergency fixes
    // Ensure each team has at least one goalkeeper
    if (teamA['Thủ môn'].length === 0 || teamB['Thủ môn'].length === 0) {
        console.warn("Critical: One team lacks a goalkeeper!");
        // Emergency: move a defender with goalkeeper skills
        const emergencyGK = playerList.find(p => p.vi_tri_phu === 'Thủ môn');
        if (emergencyGK) {
            // Move from current position to goalkeeper
            const currentTeam = teamA['Thủ môn'].length === 0 ? teamA : teamB;
            Object.keys(currentTeam).forEach(pos => {
                const index = currentTeam[pos].findIndex(p => p.ten === emergencyGK.ten);
                if (index !== -1) {
                    const player = currentTeam[pos].splice(index, 1)[0];
                    currentTeam['Thủ môn'].push({...player, assignedPosition: 'Thủ môn'});
                }
            });
        }
    }

    // Ensure each team has at least one striker
    if (teamA['Tiền đạo'].length === 0 || teamB['Tiền đạo'].length === 0) {
        console.warn("Critical: One team lacks a striker!");
        // Emergency: move a midfielder with striker skills
        const emergencyStriker = playerList.find(p => p.vi_tri_phu === 'Tiền đạo');
        if (emergencyStriker) {
            const currentTeam = teamA['Tiền đạo'].length === 0 ? teamA : teamB;
            Object.keys(currentTeam).forEach(pos => {
                const index = currentTeam[pos].findIndex(p => p.ten === emergencyStriker.ten);
                if (index !== -1) {
                    const player = currentTeam[pos].splice(index, 1)[0];
                    currentTeam['Tiền đạo'].push({...player, assignedPosition: 'Tiền đạo'});
                }
            });
        }
    }

    return { teamA, teamB };
}

function generateTeamHTML(team) {
    const positions = ['Thủ môn', 'Hậu vệ giữa', 'Hậu vệ cánh', 'Tiền vệ', 'Tiền đạo'];
    
    // Split positions into two columns for better layout
    const leftColumn = ['Thủ môn', 'Hậu vệ giữa', 'Tiền vệ'];
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
    
    html += '</div>'; // Close team-positions
    
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