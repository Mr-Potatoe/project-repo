'use client';

import { useEffect, useState } from 'react';
import {
  Container,
  Typography,
  Box,
  Grid,
  Card,
  CardContent,
  CardHeader,
  Avatar,
  IconButton,
  Tooltip,
  CircularProgress,
  Paper,
  Button,
  Link as MuiLink,
  Divider,
} from '@mui/material';
import {
  FolderOpen as FolderIcon,
  Schedule as ScheduleIcon,
  Launch as LaunchIcon,
  GitHub as GitHubIcon,
  Code as CodeIcon,
} from '@mui/icons-material';
import { format } from 'date-fns';
import Link from 'next/link';

interface Project {
  id: number;
  name: string;
  description: string;
  url?: string;
  created_at: string;
  updated_at: string;
}

export default function Home() {
  const [projects, setProjects] = useState<Project[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchProjects = async () => {
      try {
        const response = await fetch('/api/projects/public');
        if (!response.ok) throw new Error('Failed to fetch projects');
        const data = await response.json();
        console.log('Fetched projects:', data); // Debug log
        setProjects(data.data || []);
      } catch (error) {
        console.error('Error fetching projects:', error);
      } finally {
        setLoading(false);
      }
    };

    fetchProjects();
  }, []);

  if (loading) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', height: '100vh' }}>
        <CircularProgress />
      </Box>
    );
  }

  return (
    <>
      {/* Hero Section */}
      <Box
        sx={{
          bgcolor: 'background.paper',
          pt: 8,
          pb: 6,
          borderBottom: '1px solid',
          borderColor: 'divider',
        }}
      >
        <Container maxWidth="lg">
          <Typography
            component="h1"
            variant="h2"
            align="center"
            color="text.primary"
            gutterBottom
            sx={{ fontWeight: 'bold' }}
          >
            Project Repository
          </Typography>
          <Typography variant="h5" align="center" color="text.secondary" paragraph>
            Explore our collection of web development projects. Each project showcases modern full-stack development
            using Next.js, PHP, and MySQL.
          </Typography>
          <Box sx={{ mt: 4, display: 'flex', justifyContent: 'center', gap: 2 }}>
            <Button
              variant="contained"
              component={Link}
              href="/dashboard"
              startIcon={<CodeIcon />}
            >
              Dashboard
            </Button>
            <Button
              variant="outlined"
              href="https://github.com/Mr-Potatoe"
              target="_blank"
              rel="noopener noreferrer"
              startIcon={<GitHubIcon />}
            >
              GitHub
            </Button>
          </Box>
        </Container>
      </Box>

      {/* Projects Section */}
      <Container maxWidth="lg" sx={{ py: 8 }}>
        <Typography variant="h4" component="h2" sx={{ mb: 4, fontWeight: 'bold' }}>
          Featured Projects ({projects.length})
        </Typography>

        <Grid container spacing={4}>
          {projects.map((project) => (
            <Grid item xs={12} md={6} key={project.id}>
              <Card 
                sx={{ 
                  height: '100%',
                  display: 'flex',
                  flexDirection: 'column',
                  borderRadius: 2,
                  transition: 'transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out',
                  '&:hover': {
                    transform: 'translateY(-4px)',
                    boxShadow: 4,
                  },
                }}
              >
                <CardHeader
                  avatar={
                    <Avatar sx={{ bgcolor: 'primary.main' }}>
                      <FolderIcon />
                    </Avatar>
                  }
                  title={
                    <Typography variant="h6" component="div">
                      {project.name}
                    </Typography>
                  }
                  action={
                    project.url && (
                      <Tooltip title="View Project">
                        <IconButton
                          component="a"
                          href={`http://localhost/project-repo/projects/${project.url}/`}
                          target="_blank"
                          rel="noopener noreferrer"
                          size="small"
                          sx={{ color: 'primary.main' }}
                        >
                          <LaunchIcon />
                        </IconButton>
                      </Tooltip>
                    )
                  }
                />
                <CardContent sx={{ flexGrow: 1 }}>
                  <Typography
                    variant="body2"
                    color="text.secondary"
                    sx={{
                      mb: 2,
                      display: '-webkit-box',
                      WebkitLineClamp: 3,
                      WebkitBoxOrient: 'vertical',
                      overflow: 'hidden',
                    }}
                  >
                    {project.description || 'No description provided'}
                  </Typography>

                  <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                    <ScheduleIcon fontSize="small" color="action" />
                    <Typography variant="body2" color="text.secondary">
                      Updated {format(new Date(project.updated_at), 'MMM d, yyyy')}
                    </Typography>
                  </Box>
                </CardContent>
              </Card>
            </Grid>
          ))}

          {projects.length === 0 && (
            <Grid item xs={12}>
              <Paper sx={{ p: 4, textAlign: 'center' }}>
                <Typography variant="body1" color="text.secondary">
                  No projects available at the moment.
                </Typography>
              </Paper>
            </Grid>
          )}
        </Grid>
      </Container>

      {/* Footer */}
      <Box
        component="footer"
        sx={{
          py: 3,
          px: 2,
          mt: 'auto',
          backgroundColor: (theme) =>
            theme.palette.mode === 'light'
              ? theme.palette.grey[100]
              : theme.palette.grey[900],
        }}
      >
        <Container maxWidth="lg">
          <Typography variant="body2" color="text.secondary" align="center">
            {'© '}
            {new Date().getFullYear()}
            {' Project Repository. Built with Next.js, PHP, and MySQL.'}
          </Typography>
        </Container>
      </Box>
    </>
  );
}
