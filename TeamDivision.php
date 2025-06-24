<?php
// TeamDivision.php - Class thực hiện thuật toán chia đội

require_once 'config.php';

class TeamDivision {
    private $positions = ['Thủ môn', 'Trung vệ', 'Hậu vệ cánh', 'Tiền vệ', 'Tiền đạo'];
    
    public function divideTeams($players) {
        if (count($players) < MIN_PLAYERS) {
            throw new Exception("Cần ít nhất " . MIN_PLAYERS . " cầu thủ để chia đội");
        }
        
        // Initialize teams
        $teamA = [];
        $teamB = [];
        foreach ($this->positions as $pos) {
            $teamA[$pos] = [];
            $teamB[$pos] = [];
        }
        
        $usedPlayers = [];
        
        // STEP 1: Distribute players by their MAIN POSITION first
        foreach ($this->positions as $position) {
            $playersInPosition = array_filter($players, function($player) use ($position, $usedPlayers) {
                return $player['main_position'] === $position && !in_array($player['id'], $usedPlayers);
            });
            
            // Sort by skill level
            usort($playersInPosition, function($a, $b) {
                $skillValues = ['Tốt' => 3, 'Trung bình' => 2, 'Yếu' => 1];
                return $skillValues[$b['main_skill']] - $skillValues[$a['main_skill']];
            });
            
            // Special handling for goalkeepers - only 1 per team
            if ($position === 'Thủ môn') {
                if (count($playersInPosition) >= 1) {
                    $teamA[$position][] = $this->assignPlayer($playersInPosition[0], $position);
                    $usedPlayers[] = $playersInPosition[0]['id'];
                }
                if (count($playersInPosition) >= 2) {
                    $teamB[$position][] = $this->assignPlayer($playersInPosition[1], $position);
                    $usedPlayers[] = $playersInPosition[1]['id'];
                }
            }
            // Special handling for strikers - distribute ALL good strikers
            else if ($position === 'Tiền đạo') {
                $goodStrikers = array_filter($playersInPosition, function($p) {
                    return $p['main_skill'] === 'Tốt';
                });
                $okayStrikers = array_filter($playersInPosition, function($p) {
                    return $p['main_skill'] === 'Trung bình';
                });
                
                // Distribute good strikers alternately
                foreach ($goodStrikers as $index => $player) {
                    $teamASize = count($teamA[$position]);
                    $teamBSize = count($teamB[$position]);
                    
                    if ($teamASize <= $teamBSize) {
                        $teamA[$position][] = $this->assignPlayer($player, $position);
                    } else {
                        $teamB[$position][] = $this->assignPlayer($player, $position);
                    }
                    $usedPlayers[] = $player['id'];
                }
                
                // Add okay strikers if teams need more
                foreach ($okayStrikers as $player) {
                    $teamASize = count($teamA[$position]);
                    $teamBSize = count($teamB[$position]);
                    
                    if ($teamASize === 0 || $teamBSize === 0 || ($teamASize <= $teamBSize && $teamASize < 2)) {
                        if ($teamASize <= $teamBSize) {
                            $teamA[$position][] = $this->assignPlayer($player, $position);
                        } else {
                            $teamB[$position][] = $this->assignPlayer($player, $position);
                        }
                        $usedPlayers[] = $player['id'];
                    }
                }
            }
            // For other positions, distribute all players to their main positions
            else {
                foreach ($playersInPosition as $player) {
                    $teamASize = $this->getTotalPlayers($teamA);
                    $teamBSize = $this->getTotalPlayers($teamB);
                    
                    if ($teamASize <= $teamBSize) {
                        $teamA[$position][] = $this->assignPlayer($player, $position);
                    } else {
                        $teamB[$position][] = $this->assignPlayer($player, $position);
                    }
                    $usedPlayers[] = $player['id'];
                }
            }
        }
        
        // STEP 2: Handle remaining goalkeepers
        $remainingGoalkeepers = array_filter($players, function($player) use ($usedPlayers) {
            return $player['main_position'] === 'Thủ môn' && !in_array($player['id'], $usedPlayers);
        });
        
        foreach ($remainingGoalkeepers as $player) {
            $secondaryPos = $player['secondary_position'] ?: 'Hậu vệ cánh';
            $teamASize = $this->getTotalPlayers($teamA);
            $teamBSize = $this->getTotalPlayers($teamB);
            
            if ($teamASize <= $teamBSize) {
                $teamA[$secondaryPos][] = $this->assignPlayer($player, $secondaryPos);
            } else {
                $teamB[$secondaryPos][] = $this->assignPlayer($player, $secondaryPos);
            }
            $usedPlayers[] = $player['id'];
        }
        
        // STEP 3: Handle remaining strikers
        $remainingStrikers = array_filter($players, function($player) use ($usedPlayers) {
            return $player['main_position'] === 'Tiền đạo' && !in_array($player['id'], $usedPlayers);
        });
        
        foreach ($remainingStrikers as $player) {
            $secondaryPos = $player['secondary_position'] ?: 'Tiền vệ';
            $teamASize = $this->getTotalPlayers($teamA);
            $teamBSize = $this->getTotalPlayers($teamB);
            
            if ($teamASize <= $teamBSize) {
                $teamA[$secondaryPos][] = $this->assignPlayer($player, $secondaryPos);
            } else {
                $teamB[$secondaryPos][] = $this->assignPlayer($player, $secondaryPos);
            }
            $usedPlayers[] = $player['id'];
        }
        
        // STEP 4: Handle any remaining unassigned players
        $finalRemaining = array_filter($players, function($player) use ($usedPlayers) {
            return !in_array($player['id'], $usedPlayers);
        });
        
        foreach ($finalRemaining as $player) {
            $assignPosition = $player['secondary_position'] ?: $player['main_position'];
            $teamASize = $this->getTotalPlayers($teamA);
            $teamBSize = $this->getTotalPlayers($teamB);
            
            if ($teamASize <= $teamBSize) {
                $teamA[$assignPosition][] = $this->assignPlayer($player, $assignPosition);
            } else {
                $teamB[$assignPosition][] = $this->assignPlayer($player, $assignPosition);
            }
            $usedPlayers[] = $player['id'];
        }
        
        return [
            'teamA' => $teamA,
            'teamB' => $teamB,
            'stats' => [
                'totalA' => $this->getTotalPlayers($teamA),
                'totalB' => $this->getTotalPlayers($teamB),
                'gkA' => count($teamA['Thủ môn']),
                'gkB' => count($teamB['Thủ môn']),
                'strikerA' => count($teamA['Tiền đạo']),
                'strikerB' => count($teamB['Tiền đạo'])
            ]
        ];
    }
    
    private function assignPlayer($player, $assignedPosition) {
        $playerData = $player;
        $playerData['assigned_position'] = $assignedPosition;
        
        // Determine position type and skill level
        if ($player['main_position'] === $assignedPosition) {
            $playerData['position_type'] = 'Sở trường';
            $playerData['skill_level'] = $player['main_skill'];
        } else if ($player['secondary_position'] === $assignedPosition) {
            $playerData['position_type'] = 'Sở đoản';
            $playerData['skill_level'] = $player['secondary_skill'];
        } else {
            $playerData['position_type'] = 'Không quen';
            $playerData['skill_level'] = 'Yếu';
        }
        
        return $playerData;
    }
    
    private function getTotalPlayers($team) {
        $total = 0;
        foreach ($team as $position => $players) {
            $total += count($players);
        }
        return $total;
    }
    
    public function saveMatchFormation($matchDate, $teamA, $teamB) {
        $pdo = DB::getInstance();
        
        try {
            $pdo->beginTransaction();
            
            // Save daily match
            $stmt = $pdo->prepare("
                INSERT INTO daily_matches (match_date, team_a_formation, team_b_formation, status) 
                VALUES (?, ?, ?, 'scheduled')
                ON DUPLICATE KEY UPDATE 
                team_a_formation = VALUES(team_a_formation),
                team_b_formation = VALUES(team_b_formation),
                status = 'scheduled'
            ");
            
            $stmt->execute([
                $matchDate,
                json_encode($teamA, JSON_UNESCAPED_UNICODE),
                json_encode($teamB, JSON_UNESCAPED_UNICODE)
            ]);
            
            $matchId = $pdo->lastInsertId() ?: $pdo->query("SELECT id FROM daily_matches WHERE match_date = '$matchDate'")->fetchColumn();
            
            // Clear existing participants
            $pdo->prepare("DELETE FROM match_participants WHERE match_id = ?")->execute([$matchId]);
            
            // Save participants
            $stmt = $pdo->prepare("
                INSERT INTO match_participants 
                (match_id, player_id, team, assigned_position, position_type, skill_level) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            // Save team A
            foreach ($teamA as $position => $players) {
                foreach ($players as $player) {
                    $stmt->execute([
                        $matchId,
                        $player['id'],
                        'A',
                        $player['assigned_position'],
                        $player['position_type'],
                        $player['skill_level']
                    ]);
                }
            }
            
            // Save team B
            foreach ($teamB as $position => $players) {
                foreach ($players as $player) {
                    $stmt->execute([
                        $matchId,
                        $player['id'],
                        'B',
                        $player['assigned_position'],
                        $player['position_type'],
                        $player['skill_level']
                    ]);
                }
            }
            
            $pdo->commit();
            return $matchId;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
?>