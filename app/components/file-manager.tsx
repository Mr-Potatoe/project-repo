'use client';

import { useState, useEffect } from 'react';
import { useSession } from 'next-auth/react';
import {
  Box,
  List,
  ListItem,
  ListItemIcon,
  ListItemText,
  IconButton,
  Typography,
  Paper,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  TextField,
  Breadcrumbs,
  Link,
  CircularProgress,
} from '@mui/material';
import {
  Folder as FolderIcon,
  Description as FileIcon,
  Edit as EditIcon,
  Save as SaveIcon,
  Close as CloseIcon,
  ArrowBack as ArrowBackIcon,
} from '@mui/icons-material';
import { toast } from 'react-hot-toast';

interface FileItem {
  name: string;
  path: string;
  type: 'directory' | 'file';
  size: number | null;
  modified: string;
  content?: string;
}

interface FileManagerProps {
  projectId: number;
  projectPath: string;
}

export default function FileManager({ projectId, projectPath }: FileManagerProps) {
  const { data: session } = useSession();
  const [files, setFiles] = useState<FileItem[]>([]);
  const [currentPath, setCurrentPath] = useState('');
  const [selectedFile, setSelectedFile] = useState<FileItem | null>(null);
  const [fileContent, setFileContent] = useState('');
  const [isEditing, setIsEditing] = useState(false);
  const [isLoading, setIsLoading] = useState(false);

  useEffect(() => {
    loadFiles();
  }, [projectId, currentPath]);

  const loadFiles = async () => {
    try {
      setIsLoading(true);
      const response = await fetch(`/api/projects/files?project_id=${projectId}&path=${currentPath}`);
      const data = await response.json();
      
      if (!response.ok || !data.success) {
        throw new Error(data.message || 'Failed to load files');
      }
      
      // Handle array of files or single file response
      const fileData = Array.isArray(data.data) ? data.data : [data.data];
      setFiles(fileData);
    } catch (error: any) {
      toast.error(error.message || 'Failed to load files');
      console.error('Load files error:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const loadFileContent = async (file: FileItem) => {
    try {
      setIsLoading(true);
      const response = await fetch(`/api/projects/files?project_id=${projectId}&path=${file.path}`);
      const data = await response.json();
      
      if (!response.ok || !data.success) {
        throw new Error(data.message || 'Failed to load file content');
      }
      
      setFileContent(data.data.content || '');
      setSelectedFile(file);
    } catch (error: any) {
      toast.error(error.message || 'Failed to load file content');
      console.error('Load file content error:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const saveFileContent = async () => {
    if (!selectedFile) return;

    try {
      setIsLoading(true);
      const response = await fetch('/api/projects/files', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          project_id: projectId,
          path: selectedFile.path,
          content: fileContent,
        }),
      });

      const data = await response.json();
      if (!response.ok || !data.success) {
        throw new Error(data.message || 'Failed to save file');
      }

      toast.success('File saved successfully');
      setIsEditing(false);
      // Reload files to show updated modification time
      loadFiles();
    } catch (error: any) {
      toast.error(error.message || 'Failed to save file');
      console.error('Save file error:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const handleFileClick = async (file: FileItem) => {
    if (file.type === 'directory') {
      setCurrentPath(file.path);
    } else {
      await loadFileContent(file);
    }
  };

  const handleBack = () => {
    const parentPath = currentPath.split('/').slice(0, -1).join('/');
    setCurrentPath(parentPath);
  };

  const handleCloseFile = () => {
    setSelectedFile(null);
    setFileContent('');
    setIsEditing(false);
  };

  const pathParts = currentPath.split('/').filter(Boolean);

  return (
    <Box>
      <Paper sx={{ p: 2, mb: 2 }}>
        <Box sx={{ display: 'flex', alignItems: 'center', mb: 2 }}>
          {currentPath && (
            <IconButton onClick={handleBack} sx={{ mr: 1 }}>
              <ArrowBackIcon />
            </IconButton>
          )}
          <Breadcrumbs>
            <Link
              component="button"
              variant="body1"
              onClick={() => setCurrentPath('')}
              sx={{ cursor: 'pointer' }}
            >
              Root
            </Link>
            {pathParts.map((part, index) => (
              <Link
                key={part}
                component="button"
                variant="body1"
                onClick={() => setCurrentPath(pathParts.slice(0, index + 1).join('/'))}
                sx={{ cursor: 'pointer' }}
              >
                {part}
              </Link>
            ))}
          </Breadcrumbs>
        </Box>

        {isLoading ? (
          <Box sx={{ display: 'flex', justifyContent: 'center', p: 3 }}>
            <CircularProgress />
          </Box>
        ) : (
          <List>
            {files.map((file) => (
              <ListItem
                key={file.path}
                button
                onClick={() => handleFileClick(file)}
              >
                <ListItemIcon>
                  {file.type === 'directory' ? <FolderIcon /> : <FileIcon />}
                </ListItemIcon>
                <ListItemText
                  primary={file.name}
                  secondary={`Modified: ${new Date(file.modified).toLocaleString()}`}
                />
              </ListItem>
            ))}
          </List>
        )}
      </Paper>

      <Dialog
        open={!!selectedFile}
        onClose={handleCloseFile}
        maxWidth="md"
        fullWidth
      >
        <DialogTitle>
          <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
            <Typography variant="h6">{selectedFile?.name}</Typography>
            <Box>
              {!isEditing && (
                <IconButton onClick={() => setIsEditing(true)}>
                  <EditIcon />
                </IconButton>
              )}
              <IconButton onClick={handleCloseFile}>
                <CloseIcon />
              </IconButton>
            </Box>
          </Box>
        </DialogTitle>
        <DialogContent>
          {isEditing ? (
            <TextField
              multiline
              fullWidth
              minRows={10}
              value={fileContent}
              onChange={(e) => setFileContent(e.target.value)}
              disabled={isLoading}
            />
          ) : (
            <Typography
              component="pre"
              sx={{
                whiteSpace: 'pre-wrap',
                wordBreak: 'break-word',
                fontFamily: 'monospace',
              }}
            >
              {fileContent}
            </Typography>
          )}
        </DialogContent>
        {isEditing && (
          <DialogActions>
            <Button onClick={() => setIsEditing(false)}>Cancel</Button>
            <Button
              onClick={saveFileContent}
              variant="contained"
              disabled={isLoading}
              startIcon={isLoading ? <CircularProgress size={20} /> : <SaveIcon />}
            >
              Save
            </Button>
          </DialogActions>
        )}
      </Dialog>
    </Box>
  );
}
