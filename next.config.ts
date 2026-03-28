import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  images: {
    remotePatterns: [
      {
        protocol: "https",
        hostname: "lasherasdemo.washer.app",
        pathname: "/wp-content/uploads/**",
      },
      {
        protocol: "https",
        hostname: "complejofranco.com",
        pathname: "/wp-content/uploads/**",
      },
    ],
  },
};

export default nextConfig;
