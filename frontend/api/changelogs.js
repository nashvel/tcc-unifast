export default async function handler(req, res) {
  // Read safely from Vercel's backend environment
  const repo = process.env.GITHUB_REPO || "nashvel/tcc-unifast";
  const token = process.env.GITHUB_TOKEN;
  
  const headers = {
    'Accept': 'application/vnd.github.v3+json',
    'User-Agent': 'TCC-UniFAST-App'
  };
  
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }
  
  try {
    const fetchResponse = await fetch(`https://api.github.com/repos/${repo}/commits?per_page=100`, { headers });
    
    if (!fetchResponse.ok) {
      return res.status(fetchResponse.status).json({ error: "Failed to fetch commits from GitHub" });
    }
    
    const commits = await fetchResponse.json();
    
    // Cache the response at the Vercel Edge CDN for 5 minutes (300 seconds) 
    // to prevent hitting GitHub's rate limits
    res.setHeader('Cache-Control', 's-maxage=300, stale-while-revalidate');
    
    return res.status(200).json({
      data: commits,
      repo: repo,
      has_token: !!token
    });
  } catch (error) {
    return res.status(500).json({ error: error.message });
  }
}
