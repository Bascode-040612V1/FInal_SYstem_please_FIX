# Image Upload Directory
# This folder stores student profile images
# Images are organized by student ID and timestamp

# Directory structure:
# uploads/
#   student_profiles/
#     {student_id}_{timestamp}.{extension}
#
# Examples:
# - 220342_1694345678.jpg
# - 123456_1694345679.png

# Security Notes:
# - Only image files (JPEG, PNG, GIF) are allowed
# - Maximum file size: 5MB
# - Files are renamed to prevent conflicts
# - Original filenames are not preserved for security

# File Permissions:
# - Directory: 755 (rwxr-xr-x)
# - Files: 644 (rw-r--r--)

# Access:
# - Files can be accessed via HTTP: /backend/uploads/student_profiles/filename
# - Database stores relative path: uploads/student_profiles/filename