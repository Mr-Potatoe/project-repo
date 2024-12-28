'use client';

import { useState, useEffect } from 'react';
import {
  Box,
  Typography,
  Grid,
  Paper,
  Card,
  CardContent,
  CardActions,
  Button,
  List,
  ListItem,
  ListItemText,
  ListItemSecondaryAction,
  Chip,
  CircularProgress,
  Alert,
} from '@mui/material';
import {
  People as PeopleIcon,
  Folder as ProjectIcon,
  Assignment as LogIcon,
  Settings as SettingIcon,
} from '@mui/icons-material';
import { useRouter } from 'next/navigation';

interface DashboardStats {
  totalUsers: number;
  totalProjects: number;
  recentDeployments: {
    id: number;
    project_id: number;
    project_name: string;
    log_message: string;
    timestamp: string;
  }[];
  projectsByStatus: {
    queued: number;
    deployed: number;
    failed: number;
  };
}

export default function AdminDashboard() {
  const router = useRouter();
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    fetchDashboardStats();
  }, []);

  const fetchDashboardStats = async () => {
    try {
      const [usersRes, projectsRes, logsRes] = await Promise.all([
        fetch('/api/users/list'),
        fetch('/api/admin/projects'),
        fetch('/api/admin/logs')
      ]);

      const [usersData, projectsData, logsData] = await Promise.all([
        usersRes.json(),
        projectsRes.json(),
        logsRes.json()
      ]);

      if (usersData.status === 'success' && projectsData.status === 'success' && logsData.status === 'success') {
        const projectsByStatus = projectsData.data.reduce((acc: any, project: any) => {
          acc[project.status] = (acc[project.status] || 0) + 1;
          return acc;
        }, { queued: 0, deployed: 0, failed: 0 });

        setStats({
          totalUsers: usersData.data.length,
          totalProjects: projectsData.data.length,
          recentDeployments: logsData.data.slice(0, 5),
          projectsByStatus
        });
      }
    } catch (error) {
      setError('Failed to fetch dashboard statistics');
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', mt: 4 }}>
        <CircularProgress />
      </Box>
    );
  }

  if (error) {
    return (
      <Alert severity="error" sx={{ mt: 2 }}>
        {error}
      </Alert>
    );
  }

  const statCards = [
    {
      title: 'Total Users',
      value: stats?.totalUsers || 0,
      icon: <PeopleIcon sx={{ fontSize: 40 }} />,
      color: '#2196f3',
      link: '/admin/users'
    },
    {
      title: 'Total Projects',
      value: stats?.totalProjects || 0,
      icon: <ProjectIcon sx={{ fontSize: 40 }} />,
      color: '#4caf50',
      link: '/admin/projects'
    },
    {
      title: 'Queued Projects',
      value: stats?.projectsByStatus.queued || 0,
      icon: <LogIcon sx={{ fontSize: 40 }} />,
      color: '#ff9800',
      link: '/admin/projects'
    },
    {
      title: 'Failed Deployments',
      value: stats?.projectsByStatus.failed || 0,
      icon: <SettingIcon sx={{ fontSize: 40 }} />,
      color: '#f44336',
      link: '/admin/logs'
    }
  ];

  return (
    <Box>
      <Typography variant="h4" gutterBottom>
        Dashboard
      </Typography>

      <Grid container spacing={3}>
        {statCards.map((card) => (
          <Grid item xs={12} sm={6} md={3} key={card.title}>
            <Paper
              sx={{
                p: 2,
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                cursor: 'pointer',
                '&:hover': { bgcolor: 'action.hover' },
              }}
              onClick={() => router.push(card.link)}
            >
              <Box sx={{ color: card.color, mb: 1 }}>
                {card.icon}
              </Box>
              <Typography variant="h4" component="div">
                {card.value}
              </Typography>
              <Typography color="text.secondary" variant="subtitle1">
                {card.title}
              </Typography>
            </Paper>
          </Grid>
        ))}

        <Grid item xs={12} md={6}>
          <Paper sx={{ p: 2, height: '100%' }}>
            <Typography variant="h6" gutterBottom>
              Recent Deployments
            </Typography>
            <List>
              {stats?.recentDeployments.map((log) => (
                <ListItem key={log.id}>
                  <ListItemText
                    primary={log.project_name || `Project #${log.id}`}
                    secondary={log.log_message}
                  />
                  <ListItemSecondaryAction>
                    <Typography variant="caption" color="text.secondary">
                      {new Date(log.timestamp).toLocaleString()}
                    </Typography>
                  </ListItemSecondaryAction>
                </ListItem>
              ))}
            </List>
            <Box sx={{ mt: 2 }}>
              <Button 
                variant="outlined" 
                onClick={() => router.push('/admin/logs')}
              >
                View All Logs
              </Button>
            </Box>
          </Paper>
        </Grid>

        <Grid item xs={12} md={6}>
          <Paper sx={{ p: 2, height: '100%' }}>
            <Typography variant="h6" gutterBottom>
              Project Status Overview
            </Typography>
            <Box sx={{ mt: 2 }}>
              {Object.entries(stats?.projectsByStatus || {}).map(([status, count]) => (
                <Box key={status} sx={{ mb: 2, display: 'flex', alignItems: 'center', gap: 2 }}>
                  <Chip
                    label={status.toUpperCase()}
                    color={
                      status === 'deployed' ? 'success' :
                      status === 'queued' ? 'warning' :
                      'error'
                    }
                    size="small"
                  />
                  <Typography variant="body1">
                    {count} projects
                  </Typography>
                  <Box
                    sx={{
                      flexGrow: 1,
                      height: 8,
                      bgcolor: 'background.paper',
                      borderRadius: 1,
                      overflow: 'hidden',
                    }}
                  >
                    <Box
                      sx={{
                        width: `${(count / (stats?.totalProjects || 1)) * 100}%`,
                        height: '100%',
                        bgcolor: 
                          status === 'deployed' ? 'success.main' :
                          status === 'queued' ? 'warning.main' :
                          'error.main',
                      }}
                    />
                  </Box>
                </Box>
              ))}
            </Box>
            <Box sx={{ mt: 3 }}>
              <Button 
                variant="outlined" 
                onClick={() => router.push('/admin/projects')}
              >
                Manage Projects
              </Button>
            </Box>
          </Paper>
        </Grid>
      </Grid>
    </Box>
  );
}
