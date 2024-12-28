'use client';

import { useState, useRef } from 'react';
import {
  Box,
  Button,
  TextField,
  Typography,
  CircularProgress,
  FormControl,
  InputLabel,
  OutlinedInput,
  InputAdornment,
  IconButton,
  Alert,
  Stack,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  useTheme,
} from '@mui/material';
import {
  CloudUpload as CloudUploadIcon,
  Folder as FolderIcon,
  Close as CloseIcon,
} from '@mui/icons-material';
import { toast } from 'react-hot-toast';

interface ProjectUploadProps {
  open: boolean;
  onClose: () => void;
  onUploadComplete?: () => void;
}

export default function ProjectUpload({ open, onClose, onUploadComplete }: ProjectUploadProps) {
  const theme = useTheme();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const [formData, setFormData] = useState({
    name: '',
    description: '',
    database_name: '',
  });

  const handleFileSelect = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (file) {
      if (file.type !== 'application/zip' && file.type !== 'application/x-zip-compressed') {
        setError('Please upload a ZIP file');
        return;
      }
      setSelectedFile(file);
      setError(null);
    }
  };

  const handleUpload = async () => {
    setError(null);

    // Validation
    if (!formData.name.trim()) {
      setError('Project name is required');
      return;
    }
    if (!formData.database_name.trim()) {
      setError('Database name is required');
      return;
    }
    if (!selectedFile) {
      setError('Please select a project file');
      return;
    }

    // Validate database name
    if (!/^[a-zA-Z0-9_]+$/.test(formData.database_name)) {
      setError('Database name can only contain letters, numbers, and underscores');
      return;
    }

    try {
      setLoading(true);
      const uploadData = new FormData();
      uploadData.append('zipFile', selectedFile);
      uploadData.append('name', formData.name.trim());
      uploadData.append('database_name', formData.database_name.trim());
      uploadData.append('description', formData.description.trim());
      uploadData.append('uploadId', Date.now().toString());

      const response = await fetch('/api/projects/upload', {
        method: 'POST',
        body: uploadData,
      });

      const data = await response.json();

      if (response.ok) {
        toast.success(data.message || 'Project uploaded successfully!');
        handleClose();
        onUploadComplete?.();
      } else {
        throw new Error(data.message || 'Failed to upload project');
      }
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Failed to upload project';
      setError(message);
      toast.error(message);
    } finally {
      setLoading(false);
    }
  };

  const handleClose = () => {
    if (!loading) {
      setFormData({
        name: '',
        description: '',
        database_name: '',
      });
      setSelectedFile(null);
      setError(null);
      onClose();
    }
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  return (
    <Dialog
      open={open}
      onClose={handleClose}
      maxWidth="sm"
      fullWidth
      PaperProps={{
        sx: {
          borderRadius: '12px',
          p: 2,
        },
      }}
    >
      <DialogTitle sx={{ p: 0, mb: 2 }}>
        <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
          <Typography variant="h6" component="div">
            Upload New Project
          </Typography>
          <IconButton
            aria-label="close"
            onClick={handleClose}
            disabled={loading}
            size="small"
          >
            <CloseIcon />
          </IconButton>
        </Box>
      </DialogTitle>

      <DialogContent sx={{ p: 0 }}>
        <Stack spacing={3}>
          {error && (
            <Alert
              severity="error"
              sx={{ borderRadius: 1 }}
              action={
                <IconButton
                  aria-label="close"
                  color="inherit"
                  size="small"
                  onClick={() => setError(null)}
                >
                  <CloseIcon fontSize="inherit" />
                </IconButton>
              }
            >
              {error}
            </Alert>
          )}

          <TextField
            required
            fullWidth
            label="Project Name"
            name="name"
            value={formData.name}
            onChange={handleChange}
            disabled={loading}
          />

          <FormControl fullWidth required>
            <InputLabel>Database Name</InputLabel>
            <OutlinedInput
              label="Database Name"
              name="database_name"
              value={formData.database_name}
              onChange={handleChange}
              disabled={loading}
              error={formData.database_name !== '' && !/^[a-zA-Z0-9_]+$/.test(formData.database_name)}
              endAdornment={
                <InputAdornment position="end">_db</InputAdornment>
              }
            />
          </FormControl>

          <TextField
            fullWidth
            label="Description"
            name="description"
            value={formData.description}
            onChange={handleChange}
            multiline
            rows={3}
            disabled={loading}
          />

          <Box>
            <input
              type="file"
              accept=".zip"
              onChange={handleFileSelect}
              style={{ display: 'none' }}
              ref={fileInputRef}
              disabled={loading}
            />
            <Button
              variant="outlined"
              startIcon={<FolderIcon />}
              onClick={() => fileInputRef.current?.click()}
              disabled={loading}
              fullWidth
              sx={{
                py: 1.5,
                borderStyle: 'dashed',
                borderWidth: 2,
                '&:hover': {
                  borderStyle: 'dashed',
                  borderWidth: 2,
                },
              }}
            >
              {selectedFile ? selectedFile.name : 'Select Project ZIP File'}
            </Button>
            {selectedFile && (
              <Typography variant="caption" color="text.secondary" sx={{ mt: 1, display: 'block' }}>
                File size: {(selectedFile.size / (1024 * 1024)).toFixed(2)} MB
              </Typography>
            )}
          </Box>

          {loading && (
            <Box sx={{ width: '100%' }}>
              <CircularProgress size={24} sx={{ display: 'block', margin: '0 auto' }} />
            </Box>
          )}
        </Stack>
      </DialogContent>

      <DialogActions sx={{ p: 0, mt: 3 }}>
        <Button onClick={handleClose} disabled={loading}>
          Cancel
        </Button>
        <Button
          onClick={handleUpload}
          variant="contained"
          disabled={loading || !selectedFile || !formData.name.trim() || !formData.database_name.trim() || !/^[a-zA-Z0-9_]+$/.test(formData.database_name)}
          startIcon={loading ? <CircularProgress size={20} /> : <CloudUploadIcon />}
        >
          {loading ? 'Uploading...' : 'Upload Project'}
        </Button>
      </DialogActions>
    </Dialog>
  );
}
