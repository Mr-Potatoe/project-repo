'use client';

import { useState } from 'react';
import { useSession } from 'next-auth/react';
import {
  Box,
  Paper,
  Typography,
  Tabs,
  Tab,
  Button,
  TextField,
  CircularProgress,
  IconButton,
  Tooltip,
  Alert,
  Grid,
  Card,
  CardContent,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
} from '@mui/material';
import {
  Edit as EditIcon,
  Save as SaveIcon,
  Cancel as CancelIcon,
  Storage as DatabaseIcon,
  CalendarToday as CalendarIcon,
  Description as DescriptionIcon,
  Delete as DeleteIcon,
} from '@mui/icons-material';
import { toast } from 'react-hot-toast';
import { formatDistanceToNow } from 'date-fns';
import FileManager from './file-manager';

interface Project {
  id: number;
  name: string;
  description: string;
  database_name: string;
  upload_path: string;
  created_at: string;
  updated_at: string;
}

interface ProjectDetailsProps {
  project: Project;
  onUpdate: (updatedProject: Project) => void;
  onDelete?: () => void;
}

export default function ProjectDetails({ project, onUpdate, onDelete }: ProjectDetailsProps) {
  const { data: session } = useSession();
  const [activeTab, setActiveTab] = useState(0);
  const [isEditing, setIsEditing] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [editedProject, setEditedProject] = useState(project);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);

  const handleTabChange = (event: React.SyntheticEvent, newValue: number) => {
    if (!isLoading) {
      setActiveTab(newValue);
    }
  };

  const validateForm = () => {
    const newErrors: Record<string, string> = {};
    if (!editedProject.name.trim()) {
      newErrors.name = 'Project name is required';
    }
    if (!editedProject.database_name.trim()) {
      newErrors.database_name = 'Database name is required';
    } else if (!/^[a-zA-Z][a-zA-Z0-9_]*$/.test(editedProject.database_name)) {
      newErrors.database_name = 'Invalid database name format';
    }
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleEdit = () => {
    setIsEditing(true);
    setEditedProject(project);
    setErrors({});
  };

  const handleCancel = () => {
    setIsEditing(false);
    setEditedProject(project);
    setErrors({});
  };

  const handleSave = async () => {
    if (!validateForm()) return;

    try {
      setIsLoading(true);
      const response = await fetch(`/api/projects/${project.id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(editedProject),
      });

      const data = await response.json();
      if (!response.ok) throw new Error(data.message);

      onUpdate(data.project);
      setIsEditing(false);
      toast.success('Project updated successfully');
    } catch (error) {
      toast.error('Failed to update project');
      console.error(error);
    } finally {
      setIsLoading(false);
    }
  };

  const handleDelete = async () => {
    try {
      setIsLoading(true);
      const response = await fetch(`/api/projects/${project.id}`, {
        method: 'DELETE',
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || 'Failed to delete project');
      }

      toast.success('Project deleted successfully');
      onDelete?.();
    } catch (error) {
      toast.error('Failed to delete project');
      console.error(error);
    } finally {
      setIsLoading(false);
      setDeleteDialogOpen(false);
    }
  };

  return (
    <Box sx={{ width: '100%' }}>
      <Paper sx={{ p: 3, mb: 2, borderRadius: 2 }}>
        <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 3 }}>
          <Box sx={{ flex: 1 }}>
            {isEditing ? (
              <TextField
                value={editedProject.name}
                onChange={(e) => setEditedProject({ ...editedProject, name: e.target.value })}
                error={!!errors.name}
                helperText={errors.name}
                size="small"
                sx={{ minWidth: 300 }}
                disabled={isLoading}
                placeholder="Project Name"
              />
            ) : (
              <Typography variant="h5" component="h1" sx={{ fontWeight: 600 }}>
                {project.name}
              </Typography>
            )}
          </Box>
          <Box>
            {isLoading ? (
              <CircularProgress size={24} />
            ) : isEditing ? (
              <Box sx={{ display: 'flex', gap: 1 }}>
                <Tooltip title="Cancel">
                  <IconButton onClick={handleCancel} color="default" size="small">
                    <CancelIcon />
                  </IconButton>
                </Tooltip>
                <Tooltip title="Save">
                  <IconButton onClick={handleSave} color="primary" size="small">
                    <SaveIcon />
                  </IconButton>
                </Tooltip>
              </Box>
            ) : (
              <Box sx={{ display: 'flex', gap: 1 }}>
                <Tooltip title="Delete Project">
                  <IconButton onClick={() => setDeleteDialogOpen(true)} color="error" size="small">
                    <DeleteIcon />
                  </IconButton>
                </Tooltip>
                <Tooltip title="Edit Project">
                  <IconButton onClick={handleEdit} color="primary" size="small">
                    <EditIcon />
                  </IconButton>
                </Tooltip>
              </Box>
            )}
          </Box>
        </Box>

        <Tabs value={activeTab} onChange={handleTabChange} sx={{ borderBottom: 1, borderColor: 'divider', mb: 3, '& .MuiTab-root': { minHeight: '48px', textTransform: 'none' } }}>
          <Tab label="Details" disabled={isLoading} />
          <Tab label="Files" disabled={isLoading} />
        </Tabs>

        {activeTab === 0 ? (
          <Grid container spacing={3}>
            <Grid item xs={12}>
              <Card variant="outlined" sx={{ mb: 2 }}>
                <CardContent>
                  <Box sx={{ display: 'flex', alignItems: 'center', mb: 1 }}>
                    <DescriptionIcon color="primary" sx={{ mr: 1 }} />
                    <Typography variant="subtitle1" fontWeight={500}>
                      Description
                    </Typography>
                  </Box>
                  {isEditing ? (
                    <TextField
                      value={editedProject.description}
                      onChange={(e) => setEditedProject({ ...editedProject, description: e.target.value })}
                      multiline
                      rows={3}
                      fullWidth
                      disabled={isLoading}
                      placeholder="Enter project description"
                      sx={{ mt: 1 }}
                    />
                  ) : (
                    <Typography color="text.secondary" sx={{ whiteSpace: 'pre-wrap' }}>
                      {project.description || 'No description provided'}
                    </Typography>
                  )}
                </CardContent>
              </Card>
            </Grid>

            <Grid item xs={12} md={6}>
              <Card variant="outlined">
                <CardContent>
                  <Box sx={{ display: 'flex', alignItems: 'center', mb: 2 }}>
                    <DatabaseIcon color="primary" sx={{ mr: 1 }} />
                    <Typography variant="subtitle1" fontWeight={500}>
                      Database Configuration
                    </Typography>
                  </Box>
                  {isEditing ? (
                    <TextField
                      value={editedProject.database_name}
                      onChange={(e) => setEditedProject({ ...editedProject, database_name: e.target.value })}
                      error={!!errors.database_name}
                      helperText={errors.database_name || 'Must start with a letter, can contain letters, numbers, and underscores'}
                      size="small"
                      fullWidth
                      disabled={isLoading}
                    />
                  ) : (
                    <Typography color="text.secondary">
                      {project.database_name}
                    </Typography>
                  )}
                </CardContent>
              </Card>
            </Grid>

            <Grid item xs={12} md={6}>
              <Card variant="outlined">
                <CardContent>
                  <Box sx={{ display: 'flex', alignItems: 'center', mb: 2 }}>
                    <CalendarIcon color="primary" sx={{ mr: 1 }} />
                    <Typography variant="subtitle1" fontWeight={500}>
                      Timestamps
                    </Typography>
                  </Box>
                  <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                    <Typography variant="body2" color="text.secondary">
                      Created: {formatDistanceToNow(new Date(project.created_at))} ago
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                      Updated: {formatDistanceToNow(new Date(project.updated_at))} ago
                    </Typography>
                  </Box>
                </CardContent>
              </Card>
            </Grid>
          </Grid>
        ) : (
          <FileManager projectId={project.id} projectPath={project.upload_path} />
        )}
      </Paper>

      {/* Delete Confirmation Dialog */}
      <Dialog open={deleteDialogOpen} onClose={() => setDeleteDialogOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Delete Project</DialogTitle>
        <DialogContent>
          <Typography>
            Are you sure you want to delete project "{project.name}"? This action cannot be undone.
          </Typography>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDeleteDialogOpen(false)} disabled={isLoading}>
            Cancel
          </Button>
          <Button onClick={handleDelete} color="error" disabled={isLoading} startIcon={isLoading ? <CircularProgress size={20} /> : <DeleteIcon />}>
            Delete
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
}
