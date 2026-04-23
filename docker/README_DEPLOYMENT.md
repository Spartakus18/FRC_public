# Deployment Guide for Render with Docker

## Prerequisites
- Ensure you have a Docker account.
- Make sure Docker is installed on your local machine.
- Access to the code repository.

## Step 1: Clone the Repository
Open your terminal and clone the repository using:
```bash
git clone https://github.com/Spartakus18/FRC_public.git
```

## Step 2: Navigate to the Docker Directory
Change to the directory containing the Docker configuration:
```bash
cd FRC_public/docker
```

## Step 3: Build the Docker Image
Build the Docker image using the following command:
```bash
docker build -t frc_public .
```

## Step 4: Deploy the Application to Render
1. Log in to your Render account.
2. Click on the "New" button in the top right corner and select "Web Service".
3. Connect your GitHub repository when prompted.
4. Select the branch you want to deploy (usually `main`).
5. For the environment, choose Docker.
6. In the **Dockerfile** section, specify the path to the Dockerfile if it’s not in the root directory.
7. Click on **Create Web Service**.

## Step 5: Configure Environment Variables
Make sure to set any necessary environment variables on the Render dashboard under the ‘Environment’ settings if required by your application.

## Step 6: Deploy Your Service
After configuring the settings, Render will automatically build and deploy your service. You can monitor the progress in the Render dashboard.

## Step 7: Verify the Deployment
Once the deployment is complete, Render will provide a URL to access your application. Make sure everything works as expected.

---

## Troubleshooting
- Check the Render logs if the deployment fails.
- Ensure that your Dockerfile is correctly configured for your application's needs.

## Conclusion
You have successfully deployed your application to Render using Docker!