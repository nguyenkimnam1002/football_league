// js/match-result-functions.js
const MatchResult = {
    editMode: false,
    originalFormation: null,
    matchId: null,

    init(matchId) {
        this.matchId = matchId;
        this.setupEventListeners();
        this.updateTeamBalance();
        this.updateTeamCounts();
        this.initializeFormValidation();
    },

    setupEventListeners() {
        // Form submission
        const form = document.getElementById('matchResultForm');
        if (form) {
            form.addEventListener('submit', this.handleFormSubmit.bind(this));
        }

        // Auto-validate inputs
        document.querySelectorAll('.stat-input').forEach(input => {
            input.addEventListener('input', this.validateStatInput);
        });

        ['teamAScore', 'teamBScore'].forEach(id => {
            const input = document.getElementById(id);
            if (input) {
                input.addEventListener('input', this.validateScoreInput);
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', this.handleKeyboard.bind(this));

        // Prevent dropdown from closing when clicking inside
        document.querySelectorAll('.dropdown-menu').forEach(dropdown => {
            dropdown.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });

        // Auto-focus first score input
        const firstInput = document.getElementById('teamAScore');
        if (firstInput && window.canUpdate) {
            firstInput.focus();
            firstInput.select();
        }
    },

    // Toggle edit mode for team management
    toggleEditMode() {
        this.editMode = !this.editMode;
        const swapButtons = document.querySelectorAll('.swap-btn');
        const removeButtons = document.querySelectorAll('.remove-btn');
        const addPlayerSections = document.querySelectorAll('.add-player-section');
        const editModeText = document.getElementById('editModeText');
        const saveFormationBtn = document.getElementById('saveFormationBtn');
        const teamsContainer = document.getElementById('teamsContainer');
        const instructionText = document.getElementById('instructionText');
        
        if (this.editMode) {
            // Enable edit mode
            teamsContainer.classList.add('edit-mode');
            swapButtons.forEach(btn => btn.style.display = 'inline-block');
            removeButtons.forEach(btn => btn.style.display = 'inline-block');
            addPlayerSections.forEach(section => section.style.display = 'block');
            editModeText.textContent = 'Tắt chế độ đổi đội';
            saveFormationBtn.style.display = 'inline-block';
            instructionText.innerHTML = '<i class="fas fa-exchange-alt"></i> Đang ở chế độ chỉnh sửa đội hình. Có thể: đổi đội, thêm/bớt cầu thủ. Nhớ lưu đội hình mới sau khi chỉnh sửa.';
            
            // Store original formation
            this.originalFormation = this.getCurrentFormation();
            
            // Enable drag and drop
            this.enableDragAndDrop();
            
            // Update balance indicator
            this.updateTeamBalance();
        } else {
            // Disable edit mode
            teamsContainer.classList.remove('edit-mode');
            swapButtons.forEach(btn => btn.style.display = 'none');
            removeButtons.forEach(btn => btn.style.display = 'none');
            addPlayerSections.forEach(section => section.style.display = 'none');
            editModeText.textContent = 'Bật chế độ đổi đội';
            saveFormationBtn.style.display = 'none';
            instructionText.innerHTML = '<i class="fas fa-info-circle"></i> Nhập tỷ số và thống kê cầu thủ, sau đó click "Lưu kết quả" để hoàn tất.';
            
            // Disable drag and drop
            this.disableDragAndDrop();
        }
    },

    // Add player to team
    addPlayerToTeam(playerId, team) {
        if (!confirm(`Bạn có chắc muốn thêm cầu thủ này vào Đội ${team}?`)) {
            return;
        }
        
        // Show loading state
        const playerItems = document.querySelectorAll(`[data-player-id="${playerId}"]`);
        playerItems.forEach(item => {
            if (item.classList.contains('add-player-item')) {
                item.style.opacity = '0.5';
                item.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang thêm...';
            }
        });
        
        fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'add_player_to_match',
                match_id: this.matchId,
                player_id: playerId,
                team: team
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('Lỗi: ' + data.error);
                this.restorePlayerItems(playerItems);
            } else {
                this.showNotification('Thêm cầu thủ thành công!', 'success');
                
                // Remove from dropdown menus
                playerItems.forEach(item => {
                    if (item.classList.contains('add-player-item')) {
                        item.closest('li').remove();
                    }
                });
                
                // Reload page to update team lists
                setTimeout(() => {
                    location.reload();
                }, 1500);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Có lỗi xảy ra khi thêm cầu thủ');
            this.restorePlayerItems(playerItems);
        });
    },

    // Remove player from match
    removePlayerFromMatch(playerId) {
        if (!confirm('Bạn có chắc muốn loại cầu thủ này khỏi trận đấu?')) {
            return;
        }
        
        // Show loading state
        const playerElement = document.querySelector(`[data-player-id="${playerId}"]`);
        const removeBtn = playerElement.querySelector('.remove-btn');
        removeBtn.disabled = true;
        removeBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'remove_player_from_match',
                match_id: this.matchId,
                player_id: playerId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('Lỗi: ' + data.error);
                removeBtn.disabled = false;
                removeBtn.innerHTML = '<i class="fas fa-times"></i>';
            } else {
                this.showNotification('Loại cầu thủ thành công!', 'success');
                
                // Remove from UI with animation
                playerElement.style.animation = 'fadeOutScale 0.3s ease-out forwards';
                setTimeout(() => {
                    playerElement.remove();
                    this.updateTeamCounts();
                    this.updateTeamBalance();
                }, 300);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Có lỗi xảy ra khi loại cầu thủ');
            removeBtn.disabled = false;
            removeBtn.innerHTML = '<i class="fas fa-times"></i>';
        });
    },

    // Swap player between teams
    swapPlayer(playerId, currentTeam) {
        const playerRow = document.querySelector(`[data-player-id="${playerId}"]`);
        const targetTeam = currentTeam === 'A' ? 'B' : 'A';
        const targetContainer = document.getElementById(`team${targetTeam}Players`);
        
        // Update data attributes
        playerRow.setAttribute('data-team', targetTeam);
        
        // Update swap button
        const swapBtn = playerRow.querySelector('.swap-btn');
        if (targetTeam === 'A') {
            swapBtn.className = 'btn btn-sm btn-outline-primary swap-btn me-2';
            swapBtn.innerHTML = '<i class="fas fa-arrow-right"></i>';
            swapBtn.setAttribute('onclick', `MatchResult.swapPlayer(${playerId}, 'A')`);
            swapBtn.title = 'Chuyển sang đội B';
        } else {
            swapBtn.className = 'btn btn-sm btn-outline-danger swap-btn me-2';
            swapBtn.innerHTML = '<i class="fas fa-arrow-left"></i>';
            swapBtn.setAttribute('onclick', `MatchResult.swapPlayer(${playerId}, 'B')`);
            swapBtn.title = 'Chuyển sang đội A';
        }
        
        // Move to target team
        targetContainer.appendChild(playerRow);
        
        // Add animation effect
        playerRow.style.animation = 'swapAnimation 0.5s ease-in-out';
        setTimeout(() => {
            playerRow.style.animation = '';
            this.updateTeamCounts();
            this.updateTeamBalance();
        }, 500);
    },

    // Drag and drop functions
    enableDragAndDrop() {
        document.querySelectorAll('.player-row').forEach(row => {
            row.setAttribute('draggable', 'true');
        });
    },

    disableDragAndDrop() {
        document.querySelectorAll('.player-row').forEach(row => {
            row.setAttribute('draggable', 'false');
        });
    },

    allowDrop(ev) {
        ev.preventDefault();
        ev.currentTarget.classList.add('drop-zone');
    },

    drag(ev) {
        ev.dataTransfer.setData("text", ev.target.getAttribute('data-player-id'));
        ev.target.classList.add('dragging');
    },

    drop(ev, targetTeam) {
        ev.preventDefault();
        ev.currentTarget.classList.remove('drop-zone');
        
        const playerId = ev.dataTransfer.getData("text");
        const playerRow = document.querySelector(`[data-player-id="${playerId}"]`);
        const currentTeam = playerRow.getAttribute('data-team');
        
        if (currentTeam !== targetTeam) {
            this.swapPlayer(parseInt(playerId), currentTeam);
        }
        
        playerRow.classList.remove('dragging');
    },

    // Update team counts
    updateTeamCounts() {
        const teamACount = document.querySelectorAll('[data-team="A"]').length;
        const teamBCount = document.querySelectorAll('[data-team="B"]').length;
        
        document.getElementById('teamACount').textContent = teamACount;
        document.getElementById('teamBCount').textContent = teamBCount;
    },

    // Calculate and display team balance
    updateTeamBalance() {
        const teamABalance = this.calculateTeamStrength('A');
        const teamBBalance = this.calculateTeamStrength('B');
        
        const balanceA = document.getElementById('teamABalance');
        const balanceB = document.getElementById('teamBBalance');
        
        balanceA.innerHTML = `Sức mạnh: ${teamABalance.total} | Tốt: ${teamABalance.good} | TB: ${teamABalance.average} | Yếu: ${teamABalance.weak}`;
        balanceB.innerHTML = `Sức mạnh: ${teamBBalance.total} | Tốt: ${teamBBalance.good} | TB: ${teamBBalance.average} | Yếu: ${teamBBalance.weak}`;
        
        // Color coding based on balance
        const difference = Math.abs(teamABalance.total - teamBBalance.total);
        const balanceClass = difference <= 2 ? 'balance-good' : difference <= 5 ? 'balance-warning' : 'balance-danger';
        
        balanceA.className = `balance-indicator ${balanceClass}`;
        balanceB.className = `balance-indicator ${balanceClass}`;
    },

    calculateTeamStrength(team) {
        const players = document.querySelectorAll(`[data-team="${team}"]`);
        let total = 0;
        let good = 0;
        let average = 0;
        let weak = 0;
        
        players.forEach(player => {
            const skillBadge = player.querySelector('.skill-badge');
            if (skillBadge) {
                const skillLevel = skillBadge.textContent.trim();
                
                switch (skillLevel) {
                    case 'Tốt':
                        total += 3;
                        good++;
                        break;
                    case 'Trung bình':
                        total += 2;
                        average++;
                        break;
                    case 'Yếu':
                        total += 1;
                        weak++;
                        break;
                }
            }
        });
        
        return { total, good, average, weak };
    },

    // Get current formation
    getCurrentFormation() {
        const formation = { teamA: [], teamB: [] };
        
        document.querySelectorAll('[data-team="A"]').forEach(player => {
            formation.teamA.push(parseInt(player.getAttribute('data-player-id')));
        });
        
        document.querySelectorAll('[data-team="B"]').forEach(player => {
            formation.teamB.push(parseInt(player.getAttribute('data-player-id')));
        });
        
        return formation;
    },

    // Save new formation
    saveFormation() {
        if (!confirm('Bạn có chắc muốn lưu đội hình mới này?')) {
            return;
        }
        
        const saveBtn = document.getElementById('saveFormationBtn');
        const originalText = saveBtn.innerHTML;
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang lưu...';
        
        const currentFormation = this.getCurrentFormation();
        
        fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_formation',
                match_id: this.matchId,
                team_a_players: currentFormation.teamA,
                team_b_players: currentFormation.teamB
            })
        })
        .then(response => response.json())
        .then(data => {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalText;
            
            if (data.error) {
                alert('Lỗi: ' + data.error);
            } else {
                this.showNotification('Lưu đội hình mới thành công!', 'success');
                setTimeout(() => {
                    location.reload();
                }, 1500);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Có lỗi xảy ra khi lưu đội hình');
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalText;
        });
    },

    // Handle form submission
    handleFormSubmit(e) {
        e.preventDefault();
        
        const teamAScore = parseInt(document.getElementById('teamAScore').value);
        const teamBScore = parseInt(document.getElementById('teamBScore').value);
        
        if (isNaN(teamAScore) || isNaN(teamBScore)) {
            alert('Vui lòng nhập tỷ số hợp lệ');
            return;
        }
        
        if (teamAScore < 0 || teamBScore < 0) {
            alert('Tỷ số không được âm');
            return;
        }
        
        // Collect player stats
        const playerStats = {};
        document.querySelectorAll('.stat-input').forEach(input => {
            const playerId = input.dataset.player;
            const stat = input.dataset.stat;
            const value = parseInt(input.value) || 0;
            
            if (!playerStats[playerId]) {
                playerStats[playerId] = { goals: 0, assists: 0 };
            }
            playerStats[playerId][stat] = value;
        });
        
        // Confirm before saving
        if (!confirm(`Xác nhận lưu kết quả: Đội A ${teamAScore} - ${teamBScore} Đội B?`)) {
            return;
        }
        
        // Show loading state
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang lưu...';
        
        // Save match result
        fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_match_result',
                match_id: this.matchId,
                team_a_score: teamAScore,
                team_b_score: teamBScore,
                player_stats: playerStats
            })
        })
        .then(response => response.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
            
            if (data.error) {
                alert('Lỗi: ' + data.error);
            } else {
                this.showNotification('Cập nhật kết quả thành công!', 'success');
                setTimeout(() => {
                    location.reload();
                }, 2000);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Có lỗi xảy ra khi lưu kết quả');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        });
    },

    // Reset scores function
    resetScores() {
        if (confirm('Bạn có chắc muốn reset tất cả dữ liệu?')) {
            document.getElementById('teamAScore').value = 0;
            document.getElementById('teamBScore').value = 0;
            
            // Reset all player stats
            document.querySelectorAll('.stat-input').forEach(input => {
                input.value = 0;
            });
            
            this.showNotification('Đã reset tất cả dữ liệu', 'info');
        }
    },

    // Input validation
    validateStatInput() {
        if (this.value < 0) this.value = 0;
        if (this.value > 10) this.value = 10;
    },

    validateScoreInput() {
        if (this.value < 0) this.value = 0;
        if (this.value > 20) this.value = 20;
    },

    // Initialize form validation
    initializeFormValidation() {
        // Auto-validate inputs on load
        document.querySelectorAll('.stat-input').forEach(input => {
            this.validateStatInput.call(input);
        });
        
        ['teamAScore', 'teamBScore'].forEach(id => {
            const input = document.getElementById(id);
            if (input) {
                this.validateScoreInput.call(input);
            }
        });
    },

    // Handle keyboard shortcuts
    handleKeyboard(e) {
        // Ctrl/Cmd + S to save
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            const form = document.getElementById('matchResultForm');
            if (form) {
                form.dispatchEvent(new Event('submit'));
            }
        }
        
        // Escape to cancel edit mode
        if (e.key === 'Escape' && this.editMode) {
            this.toggleEditMode();
        }
        
        // Ctrl/Cmd + E to toggle edit mode
        if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
            e.preventDefault();
            const editBtn = document.getElementById('editModeBtn');
            if (editBtn) {
                this.toggleEditMode();
            }
        }
    },

    // Show notification
    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} position-fixed`;
        notification.style.cssText = `
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            animation: slideInRight 0.3s ease-out;
        `;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check' : 'info'}-circle me-2"></i>
            ${message}
            <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
        `;
        
        document.body.appendChild(notification);
        
        // Auto remove after 3 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.style.animation = 'slideOutRight 0.3s ease-out forwards';
                setTimeout(() => notification.remove(), 300);
            }
        }, 3000);
    },

    // Helper function to restore player items on error
    restorePlayerItems(playerItems) {
        playerItems.forEach(item => {
            if (item.classList.contains('add-player-item')) {
                item.style.opacity = '1';
                location.reload(); // Reload to restore original state
            }
        });
    }
};

// Global functions for onclick handlers
function toggleEditMode() {
    MatchResult.toggleEditMode();
}

function addPlayerToTeam(playerId, team) {
    MatchResult.addPlayerToTeam(playerId, team);
}

function removePlayerFromMatch(playerId) {
    MatchResult.removePlayerFromMatch(playerId);
}

function swapPlayer(playerId, currentTeam) {
    MatchResult.swapPlayer(playerId, currentTeam);
}

function saveFormation() {
    MatchResult.saveFormation();
}

function resetScores() {
    MatchResult.resetScores();
}

function allowDrop(ev) {
    MatchResult.allowDrop(ev);
}

function drag(ev) {
    MatchResult.drag(ev);
}

function drop(ev, targetTeam) {
    MatchResult.drop(ev, targetTeam);
}

// Add CSS animations
document.addEventListener('DOMContentLoaded', function() {
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
});