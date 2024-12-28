'use client';

import { useEffect, useState } from 'react';
import {
  Container,
  Typography,
  Box,
  Button,
  Grid,
  Card,
  CardContent,
  CardActions,
  Dialog,
  DialogTitle,
  DialogContent,
  CircularProgress,
  IconButton,
  Chip,
  Link,
  Paper,
  InputBase,
  Fade,
  useTheme,
  Tooltip,
  CardHeader,
  Avatar,
} from '@mui/material';
import {
  Add as AddIcon,
  Close as CloseIcon,
  Launch as LaunchIcon,
  Search as SearchIcon,
  FolderOpen as FolderIcon,
  Schedule as ScheduleIcon,
  Storage as StorageIcon,
} from '@mui/icons-material';
import DashboardLayout from '../components/dashboard-layout';
import ProjectUpload from '../components/project-upload';
import ProjectActions from '../components/project-actions';
import ProjectDetails from '../components/project-details';
import { Toaster, toast } from 'react-hot-toast';
import { format } from 'date-fns';

interface Project {
  id: number;
  name: string;
  description: string;
  database_name: string;
  upload_path: string;
  status: string;
  url?: string;
  created_at: string;
  updated_at: string;
}

export default function Dashboard() {
  const [projects, setProjects] = useState<Project[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [openUpload, setOpenUpload] = useState(false);
  const [selectedProject, setSelectedProject] = useState<Project | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const theme = useTheme();

  const fetchProjects = async () => {
    const loadingToast = toast.loading('Loading projects...');
    try {
      const response = await fetch('/api/projects');
      if (!response.ok) {
        throw new Error('Failed to load projects');
      }
      const data = await response.json();
      setProjects(data.data || []);
      toast.success('Projects loaded successfully', { id: loadingToast });
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'Failed to load projects';
      setError(errorMessage);
      toast.error(errorMessage, { id: loadingToast });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchProjects();
  }, []);

  const handleProjectUpload = async (formData: FormData) => {
    const uploadToast = toast.loading('Uploading project...');
    try {
      const response = await fetch('/api/projects/upload', {
        method: 'POST',
        body: formData,
      });

      if (!response.ok) {
        const error = await response.json();
        throw new Error(error.message || 'Failed to upload project');
      }

      await fetchProjects();
      setOpenUpload(false);
      toast.success('Project uploaded successfully', { id: uploadToast });
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'Failed to upload project';
      toast.error(errorMessage, { id: uploadToast });
    }
  };

  const handleProjectUpdate = (updatedProject: Project) => {
    setProjects(projects.map(p => p.id === updatedProject.id ? updatedProject : p));
    setSelectedProject(updatedProject);
  };

  const handleProjectClick = (project: Project) => {
    setSelectedProject(project);
  };

  const getStatusColor = (status: string) => {
    switch (status.toLowerCase()) {
      case 'active':
        return 'success';
      case 'pending':
        return 'warning';
      case 'inactive':
        return 'error';
      default:
        return 'default';
    }
  };

  const filteredProjects = projects.filter(project =>
    project.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
    project.description.toLowerCase().includes(searchQuery.toLowerCase())
  );

  if (loading) {
    return (
      <DashboardLayout>
        <Box
          sx={{
            display: 'flex',
            justifyContent: 'center',
            alignItems: 'center',
            height: '50vh',
          }}
        >
          <CircularProgress />
        </Box>
      </DashboardLayout>
    );
  }

  return (
    <DashboardLayout>
      <Box sx={{ mb: 4 }}>
        <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 3 }}>
          <Typography variant="h4" component="h1" sx={{ fontWeight: 'bold' }}>
            Projects
          </Typography>
          <Button
            variant="contained"
            startIcon={<AddIcon />}
            onClick={() => setOpenUpload(true)}
            sx={{
              borderRadius: '8px',
              textTransform: 'none',
              px: 3,
            }}
          >
            New Project
          </Button>
        </Box>

        <Paper
          sx={{
            p: '2px 4px',
            display: 'flex',
            alignItems: 'center',
            width: '100%',
            maxWidth: 600,
            mb: 4,
            borderRadius: '12px',
          }}
        >
          <IconButton sx={{ p: '10px' }}>
            <SearchIcon />
          </IconButton>
          <InputBase
            sx={{ ml: 1, flex: 1 }}
            placeholder="Search projects..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
          />
        </Paper>

        <Grid container spacing={3}>
          {filteredProjects.map((project) => (
            <Grid item xs={12} sm={6} md={4} key={project.id}>
              <Fade in={true} timeout={500}>
                <Card
                  sx={{
                    height: '100%',
                    display: 'flex',
                    flexDirection: 'column',
                    borderRadius: '12px',
                    transition: 'transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out',
                    '&:hover': {
                      transform: 'translateY(-4px)',
                      boxShadow: theme.shadows[4],
                    },
                  }}
                >
                  <CardHeader
                    avatar={
                      <Avatar sx={{ bgcolor: theme.palette.primary.main }}>
                        <FolderIcon />
                      </Avatar>
                    }
                    title={
                      <Typography variant="h6" component="div" sx={{ fontWeight: 'medium' }}>
                        {project.name}
                      </Typography>
                    }
                    action={
                      <Chip
                        label={project.status}
                        color={getStatusColor(project.status) as any}
                        size="small"
                        sx={{ borderRadius: '6px' }}
                      />
                    }
                  />
                  <CardContent sx={{ flexGrow: 1 }}>
                    <Typography
                      variant="body2"
                      color="text.secondary"
                      sx={{
                        mb: 2,
                        display: '-webkit-box',
                        WebkitLineClamp: 2,
                        WebkitBoxOrient: 'vertical',
                        overflow: 'hidden',
                      }}
                    >
                      {project.description}
                    </Typography>
                    <Box sx={{ display: 'flex', gap: 2, flexWrap: 'wrap' }}>
                      <Tooltip title="Database">
                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
                          <StorageIcon fontSize="small" color="action" />
                          <Typography variant="caption" color="text.secondary">
                            {project.database_name}
                          </Typography>
                        </Box>
                      </Tooltip>
                      <Tooltip title="Last Updated">
                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
                          <ScheduleIcon fontSize="small" color="action" />
                          <Typography variant="caption" color="text.secondary">
                            {format(new Date(project.updated_at), 'MMM d, yyyy')}
                          </Typography>
                        </Box>
                      </Tooltip>
                    </Box>
                  </CardContent>
                  <CardActions sx={{ p: 2, pt: 0 }}>
                    <Button
                      size="small"
                      onClick={() => handleProjectClick(project)}
                      sx={{ textTransform: 'none' }}
                    >
                      View Details
                    </Button>
                    {project.url && (
                      <Tooltip title="Open project">
                        <IconButton
                          size="small"
                          href={`http://localhost/project-repo/projects/${project.url}/`}
                          target="_blank"
                          rel="noopener noreferrer"
                          sx={{ ml: 'auto' }}
                        >
                          <LaunchIcon fontSize="small" />
                        </IconButton>
                      </Tooltip>
                    )}
                  </CardActions>
                </Card>
              </Fade>
            </Grid>
          ))}
        </Grid>
      </Box>

      {/* Upload Dialog */}
      <Dialog
        open={openUpload}
        onClose={() => setOpenUpload(false)}
        maxWidth="sm"
        fullWidth
        PaperProps={{
          sx: {
            borderRadius: '12px',
            p: 2
          },
        }}
      >
        <DialogTitle sx={{ pb: 0 }}>
          <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
            <Typography variant="h6">Upload New Project</Typography>
            <IconButton onClick={() => setOpenUpload(false)} size="small">
              <CloseIcon />
            </IconButton>
          </Box>
        </DialogTitle>
        <DialogContent sx={{ pt: 2 }}>
          <ProjectUpload
            open={openUpload}
            onClose={() => setOpenUpload(false)}
            onUpload={handleProjectUpload}
            onUploadComplete={fetchProjects}
          />
        </DialogContent>
      </Dialog>

      {/* Project Details Dialog */}
      <Dialog
        open={Boolean(selectedProject)}
        onClose={() => setSelectedProject(null)}
        maxWidth="md"
        fullWidth
        PaperProps={{
          sx: {
            borderRadius: '12px',
            p: 2
          },
        }}
      >
        <DialogTitle>
          <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
            <Typography variant="h6">Project Details</Typography>
            <IconButton onClick={() => setSelectedProject(null)} size="small">
              <CloseIcon />
            </IconButton>
          </Box>
        </DialogTitle>
        <DialogContent sx={{ pt: 2 }}>
          {selectedProject && (
            <ProjectDetails
              project={selectedProject}
              onUpdate={handleProjectUpdate}
              onDelete={() => {
                setSelectedProject(null);
                fetchProjects();
              }}
            />
          )}
        </DialogContent>
      </Dialog>
    </DashboardLayout>
  );
}